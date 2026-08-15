<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Contracts\RefundAdapter;
use Kode\MiniApp\Union\Contracts\CryptoAdapter;
use Kode\MiniApp\Union\Contracts\WebhookAdapter;
use Kode\Pays\Core\PayException;

/**
 * kode/pays 桥接工厂
 *
 * 把 miniapp 的「身份层」接入企业级聚合支付 SDK kode/pays 的便捷入口。
 * 2.0 起支付能力完全由 kode/pays 承载，本工厂是 miniapp 侧唯一、可选的单向胶水。
 * 详见 {@see PaysBridgePayAdapter}。
 *
 * 典型用法：
 * ```php
 * // 1) 一行调用：用 Kernel 中已配置的凭证自动拼装 kode/pays config（微信 / 支付宝 / 抖音 / QQ）
 * $pay = $kernel->union()->wechat()->pay();
 * $res = $pay->createOrder([...]);   // 与 kode/pays 调用方式完全一致
 *
 * // 2) 自定义凭证来源（当你希望单独维护 kode/pays config，或覆盖百度 / 企业微信等渠道时）
 * $pay = PaysBridge::adapter(Channel::BaiduMini, fn () => [
 *     'app_id' => '...', 'key' => '...',
 * ]);
 * ```
 *
 * ⚠️ kode/pays 为硬依赖：未安装时 {@see PaysBridgePayAdapter::createOrder()} 会抛清晰异常，
 * 引导业务侧先 `composer require kode/pays`。
 */
final class PaysBridge
{
    /**
     * kode/pays 是否已安装可用
     */
    public static function available(): bool
    {
        return class_exists('Kode\\Pays\\Facade\\Pay');
    }

    /**
     * 统一调用 kode/pays 网关方法，将其 {@see PayException} 归一化为本包 {@see ApiException}。
     *
     * 此前网关在「参数校验 / 签名 / 报文拼装 / 响应解析」阶段抛出的 Kode\Pays\Core\PayException
     * 会以原始类型直接冒泡，与本包「平台业务错误统一为 ApiException（无静默成功）」的约定不一致。
     * 本方法在桥接层**唯一出口**捕获并包裹为 ApiException（保留原始 code / message / gateway 原始
     * 错误码与错误信息，并链入原始异常作为 previous），使业务侧只需捕获 ApiException 一种类型即可
     * 覆盖身份层与支付层全部业务错误，无需感知 kode/pays 的异常体系。
     *
     * 仅归一化网关业务异常（PayException）；桥接层自身的契约错误
     * （未安装 kode/pays、渠道不支持某能力、付款人渠道不匹配等抛出的 RuntimeException /
     * InvalidArgumentException）仍按原样抛出，不受影响。
     *
     * @template T
     * @param \Closure():T $fn
     * @return T
     */
    public static function invokeGateway(\Closure $fn, Channel $channel, string $capability): mixed
    {
        try {
            return $fn();
        } catch (PayException $e) {
            throw new ApiException(
                $e->getMessage(),
                $e->getCode(),
                null,
                [
                    'gateway'          => $channel->label(),
                    'capability'       => $capability,
                    'gateway_code'     => $e->getGatewayCode(),
                    'gateway_message'  => $e->getGatewayMessage(),
                ],
                "支付[{$channel->label()}]{$capability}",
                $e,
            );
        }
    }

    /**
     * 用自定义 config resolver 构造桥接适配器
     *
     * @param \Closure(Channel):array<string, mixed> $resolver
     */
    public static function adapter(Channel $channel, \Closure $resolver): PaysBridgePayAdapter
    {
        return new PaysBridgePayAdapter($channel, $resolver);
    }

    /**
     * 从 miniapp Kernel 凭证自动拼装 kode/pays config 的桥接适配器
     *
     * 默认 resolver 覆盖微信 / 支付宝 / 抖音 / QQ；百度 / 企业微信等 kode/pays 暂未覆盖的渠道
     * 请使用 {@see self::adapter()} 注入自定义 resolver（{@see self::kernelResolver()} 会抛清晰引导）。
     */
    public static function adapterForKernel(Channel $channel, KernelInterface $kernel): PaysBridgePayAdapter
    {
        return new PaysBridgePayAdapter($channel, self::kernelResolver($kernel));
    }

    /**
     * 用自定义 config resolver 构造「回调验签」桥接适配器（与 {@see self::adapter()} 对称）
     *
     * 返回的 {@see PaysBridgeNotifyAdapter} 实现 {@see NotifyAdapter}，其 `decode()` 委托
     * kode/pays 完成验签 + 解密，与 {@see self::adapter()} 共用同一凭证 source。
     */
    public static function notifyAdapter(Channel $channel, \Closure $resolver): PaysBridgeNotifyAdapter
    {
        return new PaysBridgeNotifyAdapter($channel, new PaysBridgePayAdapter($channel, $resolver));
    }

    /**
     * 从 miniapp Kernel 凭证自动拼装 kode/pays config 的「回调验签」桥接适配器
     *
     * 与 {@see self::adapterForKernel()} 对称：下单走 {@see PaysBridgePayAdapter}，
     * 回调验签走 {@see PaysBridgeNotifyAdapter}，二者共用同一份 Kernel 凭证与渠道映射。
     */
    public static function notifyAdapterForKernel(Channel $channel, KernelInterface $kernel): PaysBridgeNotifyAdapter
    {
        return new PaysBridgeNotifyAdapter($channel, self::adapterForKernel($channel, $kernel));
    }

    /**
     * 用自定义 config resolver 构造「Webhook 事件」桥接适配器（与 {@see self::adapter()} 对称）
     *
     * 返回的 {@see PaysBridgeWebhookAdapter} 实现 {@see WebhookAdapter}，其 `verify()` / `parse()`
     * 委托 kode/pays 网关的 WebhookCapableInterface 方法完成异步事件验签 + 解析，
     * 与 {@see self::adapter()} 共用同一凭证 source。
     */
    public static function webhookAdapter(Channel $channel, \Closure $resolver): PaysBridgeWebhookAdapter
    {
        return new PaysBridgeWebhookAdapter($channel, new PaysBridgePayAdapter($channel, $resolver));
    }

    /**
     * 从 miniapp Kernel 凭证自动拼装 kode/pays config 的「Webhook 事件」桥接适配器
     *
     * 与 {@see self::adapterForKernel()} 对称：下单走 {@see PaysBridgePayAdapter}，
     * 异步事件走 {@see PaysBridgeWebhookAdapter}，二者共用同一份 Kernel 凭证与渠道映射。
     */
    public static function webhookAdapterForKernel(Channel $channel, KernelInterface $kernel): PaysBridgeWebhookAdapter
    {
        return new PaysBridgeWebhookAdapter($channel, self::adapterForKernel($channel, $kernel));
    }

    /**
     * 用自定义 config resolver 构造「退款」桥接适配器（与 {@see self::adapter()} 对称）
     *
     * 返回的 {@see PaysBridgeRefundAdapter} 实现 {@see RefundAdapter}，其 `applyRefund()` /
     * `queryRefund()` / `cancelRefund()` 委托 kode/pays 网关的 RefundCapableInterface 方法完成
     * 退款闭环（申请 / 查询 / 取消），与 {@see self::adapter()} 共用同一凭证 source。
     */
    public static function refundAdapter(Channel $channel, \Closure $resolver): PaysBridgeRefundAdapter
    {
        return new PaysBridgeRefundAdapter($channel, new PaysBridgePayAdapter($channel, $resolver));
    }

    /**
     * 从 miniapp Kernel 凭证自动拼装 kode/pays config 的「退款」桥接适配器
     *
     * 与 {@see self::adapterForKernel()} 对称：下单走 {@see PaysBridgePayAdapter}，
     * 退款闭环走 {@see PaysBridgeRefundAdapter}，二者共用同一份 Kernel 凭证与渠道映射。
     */
    public static function refundAdapterForKernel(Channel $channel, KernelInterface $kernel): PaysBridgeRefundAdapter
    {
        return new PaysBridgeRefundAdapter($channel, self::adapterForKernel($channel, $kernel));
    }

    /**
     * 用自定义 config resolver 构造「加密货币」桥接适配器（与 {@see self::adapter()} 对称）
     *
     * 返回的 {@see PaysBridgeCryptoAdapter} 实现 {@see CryptoAdapter}，其加密货币能力方法
     * （createCryptoOrder / getPaymentAddresses / getExchangeRate / ...）委托 kode/pays 网关的
     * CryptoCapableInterface，与 {@see self::adapter()} 共用同一凭证 source。
     *
     * 注意：加密货币（Coinbase 等）不在 miniapp Kernel 的默认渠道凭证体系内，故必须注入自定义
     * resolver（见 {@see self::kernelResolver()} 对 crypto 渠道的引导），或改用 {@see self::cryptoAdapterForKernel()}
     * 时由业务侧先行 registerCryptoAdapter。
     */
    public static function cryptoAdapter(Channel $channel, \Closure $resolver): PaysBridgeCryptoAdapter
    {
        return new PaysBridgeCryptoAdapter($channel, new PaysBridgePayAdapter($channel, $resolver));
    }

    /**
     * 从 miniapp Kernel 凭证自动拼装 kode/pays config 的「加密货币」桥接适配器
     *
     * 与 {@see self::adapterForKernel()} 对称：但加密货币渠道（Channel::Crypto）的 Kernel resolver
     * 会抛清晰引导（miniapp Kernel 无 crypto platform 配置），故该入口要求业务侧已通过
     * {@see \Kode\MiniApp\Union\Union::registerCryptoAdapter()} 注册适配器，或改用
     * {@see self::cryptoAdapter()} 注入自定义 resolver。
     */
    public static function cryptoAdapterForKernel(Channel $channel, KernelInterface $kernel): PaysBridgeCryptoAdapter
    {
        return new PaysBridgeCryptoAdapter($channel, self::adapterForKernel($channel, $kernel));
    }

    /**
     * 默认凭证解析：把 miniapp 的 Kernel 配置翻译为 kode/pays 网关 config
     *
     * 字段映射要点：
     *  - 微信商户 v2 密钥 miniapp 字段为 `key`，kode/pays 需要 `api_key`。
     *  - 仅挑选非空字段，避免把空字符串透传给 kode/pays 触发校验错误。
     *
     * @return \Closure(Channel):array<string, mixed>
     */
    public static function kernelResolver(KernelInterface $kernel): \Closure
    {
        return function (Channel $channel) use ($kernel): array {
            return match (true) {
                $channel === Channel::WechatMp, $channel === Channel::WechatMini,
                $channel === Channel::WechatH5, $channel === Channel::WechatPc,
                $channel === Channel::WechatApp, $channel === Channel::WechatOpen => self::wechatConfig($kernel),
                $channel === Channel::AlipayMini, $channel === Channel::AlipayMp,
                $channel === Channel::AlipayApp => self::alipayConfig($kernel),
                $channel === Channel::DouyinMini, $channel === Channel::DouyinMp => self::douyinConfig($kernel),
                $channel === Channel::Qq => self::qqConfig($kernel),
                $channel === Channel::BaiduMini,
                $channel === Channel::WechatWork => throw new \InvalidArgumentException(
                    "kode/pays 桥接的默认 Kernel resolver 暂未覆盖渠道 [{$channel->label()}]，"
                    . '请使用 PaysBridge::adapter() 注入自定义 config resolver（kode/pays 该渠道 config 字段待核实）',
                ),
                $channel === Channel::Crypto => throw new \InvalidArgumentException(
                    "kode/pays 桥接的默认 Kernel resolver 暂未覆盖加密货币渠道 [crypto]（miniapp Kernel 无 crypto platform 配置）。"
                    . '请使用 Union::crypto(channel, resolver) 注入自定义 config resolver，或 registerCryptoAdapter() 注册适配器',
                ),
                default => throw new \InvalidArgumentException(
                    "kode/pays 桥接的默认 Kernel resolver 暂未覆盖渠道 [{$channel->label()}]，"
                    . '请使用 PaysBridge::adapter() 注入自定义 config resolver',
                ),
            };
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function wechatConfig(KernelInterface $kernel): array
    {
        $all = $kernel->wechat()->app()->config()->all();

        return array_filter(
            [
                'app_id'     => $all['app_id'] ?? '',
                'mch_id'     => $all['mch_id'] ?? '',
                'api_key'    => $all['key'] ?? '',
                'api_v3_key' => $all['api_v3_key'] ?? null,
                'cert_path'  => $all['cert_path'] ?? null,
                'key_path'   => $all['key_path'] ?? null,
            ],
            static fn ($v) => $v !== null && $v !== '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function alipayConfig(KernelInterface $kernel): array
    {
        $all = $kernel->alipay()->app()->config()->all();

        return array_filter(
            [
                'app_id'      => $all['app_id'] ?? '',
                'private_key' => $all['private_key'] ?? '',
                'public_key'  => $all['public_key'] ?? '',
                'sandbox'     => $all['sandbox'] ?? null,
            ],
            static fn ($v) => $v !== null && $v !== '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function douyinConfig(KernelInterface $kernel): array
    {
        $all = $kernel->douyin()->app()->config()->all();

        return array_filter(
            [
                'app_id' => $all['app_id'] ?? '',
                'secret' => $all['secret'] ?? '',
                'salt'   => $all['salt'] ?? null,
            ],
            static fn ($v) => $v !== null && $v !== '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function qqConfig(KernelInterface $kernel): array
    {
        $all = $kernel->qq()->app()->config()->all();

        return array_filter(
            [
                'app_id' => $all['app_id'] ?? '',
                'secret' => $all['secret'] ?? '',
            ],
            static fn ($v) => $v !== '',
        );
    }
}
