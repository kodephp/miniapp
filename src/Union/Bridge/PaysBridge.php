<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;
use Kode\MiniApp\Union\Contracts\PayAdapter;

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
