<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\RefundAdapter;

/**
 * kode/pays 桥接退款适配器（与 {@see PaysBridgeNotifyAdapter} / {@see PaysBridgeWebhookAdapter} 对称）
 *
 * 把业务侧的「退款闭环」（申请 / 查询 / 取消）交给 kode/pays 完成，与
 * {@see \Kode\MiniApp\Union\Union::pay()} 共用同一套凭证与渠道映射——下单、退款、查询、关单
 * 都落在 kode/pays（「收钱」层）。
 *
 * 与 {@see PaysBridgePayAdapter::refund()}（核心「申请退款」，对齐网关 `refund()`）不同，本适配器
 * 暴露 kode/pays 网关 {@see \Kode\Pays\Contract\RefundCapableInterface} 的**完整退款能力**：
 * `applyRefund` / `queryRefund` / `cancelRefund`（后者仅部分网关支持，如 Stripe）。
 *
 * 设计要点：
 *  - 2.0 起本适配器是退款闭环的**唯一**实现（{@see RefundAdapter} 契约）。其三个方法直接委托底层
 *    kode/pays 网关的 RefundCapableInterface 方法，网关不支持时抛清晰异常，绝不会触发
 *    「Call to undefined method」。
 *  - 复用同一个 kode/pays 网关实例（与 `Union::pay()` 同一个 resolver），无需重复拼装 config。
 *  - 未安装 kode/pays、或当前渠道网关未实现对应方法时调用即抛清晰异常，
 *    引导业务侧先 `composer require kode/pays` 或换用支持的渠道。
 *
 * 典型用法：
 * ```php
 * // 装了 kode/pays：退款闭环一步到位（推荐）
 * $refund = $kernel->union()->wechat()->refund();
 * $res    = $refund->applyRefund(['out_trade_no' => '原支付商户单号', 'out_refund_no' => '商户退款单号', 'amount' => 100]);
 * $info   = $refund->queryRefund('商户退款单号');
 * ```
 */
final class PaysBridgeRefundAdapter implements RefundAdapter
{
    public function __construct(
        private readonly Channel $channel,
        private readonly PaysBridgePayAdapter $pay,
    ) {
    }

    #[\Override]
    public function channel(): Channel
    {
        return $this->channel;
    }

    /**
     * 申请退款（委托 kode/pays 网关 applyRefund）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function applyRefund(array $params): array
    {
        return $this->callRefundFeature('applyRefund', '退款', $params);
    }

    /**
     * 查询退款结果（委托 kode/pays 网关 queryRefund）
     *
     * @param string $outRefundNo 商户退款单号
     * @return array<string, mixed>
     */
    #[\Override]
    public function queryRefund(string $outRefundNo): array
    {
        return $this->callRefundFeature('queryRefund', '退款', $outRefundNo);
    }

    /**
     * 取消退款（委托 kode/pays 网关 cancelRefund，仅部分网关支持，如 Stripe）
     *
     * @param string $outRefundNo 商户退款单号
     * @return array<string, mixed>
     */
    #[\Override]
    public function cancelRefund(string $outRefundNo): array
    {
        return $this->callRefundFeature('cancelRefund', '退款', $outRefundNo);
    }

    /**
     * 委托真实 kode/pays 网关的退款方法（applyRefund / queryRefund / cancelRefund）
     *
     * 以 `method_exists` 守卫：仅当当前渠道的网关真正实现了该方法时才转发，
     * 否则抛清晰异常（如百度 / 企业微信网关未实现、或某平台暂未开通相关能力），
     * 避免「Call to undefined method」这类难以定位的致命错误。
     *
     * @param string       $method      网关原生方法名
     * @param string       $capability  能力中文名（用于异常提示）
     * @param mixed        ...$args     透传给网关方法的参数
     * @return array<string, mixed>
     */
    private function callRefundFeature(string $method, string $capability, mixed ...$args): array
    {
        /** @var mixed $gateway */
        $gateway = $this->pay->gateway();

        if (!is_object($gateway) || !method_exists($gateway, $method)) {
            throw new \RuntimeException(
                "渠道 [{$this->channel->label()}] 的支付网关不支持 [{$capability}] 能力（未实现 {$method}）",
            );
        }

        /** @var callable(mixed...):array<string, mixed> $fn */
        $fn = [$gateway, $method];

        /** @var array<string, mixed> $result */
        $result = $fn(...$args);

        return $result;
    }
}
