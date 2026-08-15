<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;

/**
 * 退款能力适配器接口（委托 kode/pays 网关 RefundCapableInterface）
 *
 * 与 {@see PayAdapter::refund()}（核心「申请退款」，对齐 kode/pays 网关 `refund()`）不同，本接口
 * 暴露 kode/pays 网关的 {@see \Kode\Pays\Contract\RefundCapableInterface} 完整退款能力：
 * `applyRefund`（申请退款）/ `queryRefund`（查询退款）/ `cancelRefund`（取消退款）。其中
 * `cancelRefund` 仅部分网关支持（如 Stripe）；微信 / 支付宝 / PayPal 网关已实现 `cancelRefund`
 * 并在未支持时抛 {@see \Kode\Pays\Core\PayException::methodNotSupported}。
 *
 * 与 {@see WebhookAdapter}（异步事件）、{@see NotifyAdapter}（同步结果验签）对称，本适配器面向
 * 业务侧的「退款闭环」需求：申请 / 查询 / 取消，全部委托 kode/pays 网关原生方法，miniapp 只做转发。
 *
 * 唯一实现 {@see \Kode\MiniApp\Union\Bridge\PaysBridgeRefundAdapter}：以 `method_exists` 守卫委托
 * kode/pays 网关的 `applyRefund` / `queryRefund` / `cancelRefund`，网关不支持时抛清晰异常，
 * 绝不会触发「Call to undefined method」。
 *
 * 典型用法：
 * ```php
 * $refund = Union::wechat()->refund();              // 取得退款适配器
 * $res    = $refund->applyRefund([                  // 申请退款
 *     'out_trade_no'  => '原支付商户单号',
 *     'out_refund_no' => '商户退款单号',
 *     'amount'        => 100,
 * ]);
 * $info   = $refund->queryRefund('商户退款单号');   // 查询退款
 * ```
 */
interface RefundAdapter
{
    public function channel(): Channel;

    /**
     * 申请退款（委托 kode/pays 网关 applyRefund）
     *
     * @param array<string, mixed> $params 退款参数（out_refund_no / refund_fee 必填，
     *                                      out_trade_no 与 transaction_id 至少其一）
     * @return array<string, mixed> 退款结果
     */
    public function applyRefund(array $params): array;

    /**
     * 查询退款结果（委托 kode/pays 网关 queryRefund）
     *
     * @param string $outRefundNo 商户退款单号
     * @return array<string, mixed>
     */
    public function queryRefund(string $outRefundNo): array;

    /**
     * 取消退款（委托 kode/pays 网关 cancelRefund，仅部分网关支持，如 Stripe）
     *
     * @param string $outRefundNo 商户退款单号
     * @return array<string, mixed>
     */
    public function cancelRefund(string $outRefundNo): array;
}
