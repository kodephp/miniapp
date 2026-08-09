<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * kode/pays 桥接工厂
 *
 * 把 miniapp 的「基础支付」接入企业级聚合支付 SDK kode/pays 的便捷入口。
 * 详见 {@see PaysBridgePayAdapter}。
 *
 * 典型用法：
 * ```php
 * // 1) 一行切换：用 Kernel 中已配置的凭证自动拼装 kode/pays config
 * $pay = $kernel->union()->wechat()->payViaPays();
 * $res = $pay->unifiedOrder([...]);   // 与 $union->pay() 调用方式完全一致
 *
 * // 2) 自定义凭证来源（当你希望单独维护 kode/pays config 时）
 * $pay = PaysBridge::adapter(Channel::WechatMini, fn () => [
 *     'app_id' => 'wx...', 'mch_id' => '...', 'api_key' => '...',
 * ]);
 * ```
 *
 * ⚠️ kode/pays 为可选依赖：未安装时 {@see PaysBridgePayAdapter::unifiedOrder()} 会抛清晰异常，
 * 业务侧回退到基础支付适配器即可；本工厂不强制要求 kode/pays 存在（仅 {@see available()} 反映其状态）。
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
     * 默认 resolver 覆盖微信 / 支付宝；抖音 / QQ 的 kode/pays 配置字段尚未经源码核实，
     * 请使用 {@see self::adapter()} 注入自定义 resolver。
     */
    public static function adapterForKernel(Channel $channel, KernelInterface $kernel): PaysBridgePayAdapter
    {
        return new PaysBridgePayAdapter($channel, self::kernelResolver($kernel));
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
                $channel === Channel::WechatApp, $channel === Channel::WechatOpen,
                $channel === Channel::WechatWork => self::wechatConfig($kernel),
                $channel === Channel::AlipayMini, $channel === Channel::AlipayMp,
                $channel === Channel::AlipayApp => self::alipayConfig($kernel),
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
}
