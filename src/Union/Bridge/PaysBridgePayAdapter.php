<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * kode/pays 桥接支付适配器
 *
 * 把 miniapp 的「基础支付」一键切换到企业级聚合支付 SDK {@see https://github.com/kodephp/pays kode/pays}，
 * 从而获得更健壮的下单 / 回调验签 / 退款 / 对账 / 沙箱 / 事件能力，而不必重写业务侧调用代码。
 *
 * 设计要点：
 *  - 实现与 {@see PayAdapter} 完全相同的契约（unifiedOrder(array):array），返回「平台原始数组」，
 *    因此现有调用方无需任何改动即可切换。
 *  - **可选依赖**：kode/pays 当前并非本包硬依赖。适配器在 unifiedOrder 时才探测
 *    `Kode\Pays\Facade\Pay` 是否存在；未安装时抛出清晰异常，业务侧回退到基础支付适配器即可。
 *  - 凭证来源由外部注入的 {@see $configResolver} 提供（闭包），本类不耦合 miniapp 的配置结构，
 *    便于业务侧按 kode/pays 真实要求的字段自行拼装 config。
 *  - 微信商户 v2 密钥在 miniapp 中字段名为 `key`，需映射为 kode/pays 的 `api_key`，
 *    见 {@see PaysBridge::kernelResolver()} 的默认实现。
 */
final class PaysBridgePayAdapter implements PayAdapter
{
    /**
     * kode/pays 门面类名（用变量拼接避免 PHPStan 在类未安装时报「类不存在」）
     */
    private const PAYS_FACADE = 'Kode\\Pays\\Facade\\Pay';

    /**
     * @param \Closure(Channel):array<string, mixed> $configResolver 返回 kode/pays 网关 config 数组
     */
    public function __construct(
        private readonly Channel $channel,
        private readonly \Closure $configResolver,
    ) {
    }

    public function channel(): Channel
    {
        return $this->channel;
    }

    /**
     * 统一下单（委托 kode/pays）
     *
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function unifiedOrder(array $order): array
    {
        $facade = self::PAYS_FACADE;
        if (!class_exists($facade)) {
            throw new \RuntimeException(
                '健壮支付桥接需要安装 kode/pays（执行 `composer require kode/pays`），'
                . '或改用 miniapp 基础支付适配器 $union->pay()',
            );
        }

        /** @var array<string, mixed> $config */
        $config = ($this->configResolver)($this->channel);

        $gatewayClass = $facade;
        $method       = self::gatewayMethod($this->channel);

        /** @var mixed $gateway */
        $gateway = $gatewayClass::$method($config);

        /** @var array<string, mixed> $result */
        $result = $gateway->createOrder($order);

        return $result;
    }

    /**
     * 渠道 → kode/pays 网关静态方法名
     */
    private static function gatewayMethod(Channel $channel): string
    {
        return match ($channel) {
            Channel::WechatMp, Channel::WechatMini, Channel::WechatH5,
            Channel::WechatPc, Channel::WechatApp, Channel::WechatOpen, Channel::WechatWork => 'wechat',
            Channel::AlipayMini, Channel::AlipayMp, Channel::AlipayApp => 'alipay',
            Channel::DouyinMini, Channel::DouyinMp => 'douyin',
            Channel::Qq => 'qq',
            default => throw new \InvalidArgumentException(
                "kode/pays 桥接暂不支持渠道 [{$channel->label()}]",
            ),
        };
    }
}
