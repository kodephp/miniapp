<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Core\AbstractGateway;

/**
 * 提供 applyRefund / queryRefund / cancelRefund 方法的最小支付网关，用于验证退款委托链路。
 *
 * 与 {@see WebhookCapableTestGateway} 同理：以「方法存在」形式模拟一个支持 RefundCapableInterface
 * 的网关（kode/pays 2.3.0 真实微信网关已 implements 该接口，但此处用夹具隔离网络依赖），
 * 与 miniapp 桥接的 method_exists 守卫一致。
 *
 * @internal 仅测试使用
 */
final class RefundCapableTestGateway extends AbstractGateway
{
    protected function getBaseUrl(): string
    {
        return 'https://example.com/';
    }

    protected function parseResponse(string $response): array
    {
        return ['raw' => $response];
    }

    #[\Override]
    public function createOrder(array $params): array
    {
        return $params;
    }

    #[\Override]
    public function queryOrder(string $orderId): array
    {
        return ['order_id' => $orderId];
    }

    #[\Override]
    public function refund(array $params): array
    {
        return $params;
    }

    #[\Override]
    public function verifyNotify(array $data): bool
    {
        return true;
    }

    #[\Override]
    public function closeOrder(string $orderId): array
    {
        return ['order_id' => $orderId];
    }

    /**
     * 申请退款（模拟 kode/pays 网关 applyRefund）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function applyRefund(array $params): array
    {
        return array_merge(['_method' => 'applyRefund'], $params);
    }

    /**
     * 查询退款（模拟 kode/pays 网关 queryRefund；同时作为核心 PayAdapter::queryRefund 映射目标）
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function queryRefund(string $outRefundNo): array
    {
        return ['out_refund_no' => $outRefundNo, '_method' => 'queryRefund'];
    }

    /**
     * 取消退款（模拟 kode/pays 网关 cancelRefund）
     *
     * @return array<string, mixed>
     */
    public function cancelRefund(string $outRefundNo): array
    {
        return ['out_refund_no' => $outRefundNo, '_method' => 'cancelRefund'];
    }

    public static function getName(): string
    {
        return 'wechat';
    }
}
