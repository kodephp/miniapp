<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Core\AbstractGateway;

/**
 * 提供 verifyWebhook / parseWebhook 方法的最小支付网关，用于验证 Webhook 委托链路。
 *
 * 注意：kode/pays 2.3.0（packagist 已发布版）尚未提供 WebhookCapableInterface，故本夹具
 * 仅以「方法存在」形式模拟一个支持 Webhook 的网关，与 miniapp 桥接的 method_exists 守卫一致。
 * 签名校验简化为「payload === 约定密文」；解析返回统一事件结构（与 kode/pays 网关契约一致）。
 *
 * @internal 仅测试使用
 */
final class WebhookCapableTestGateway extends AbstractGateway
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
    public function queryRefund(string $refundId): array
    {
        return ['refund_id' => $refundId];
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
     * @param array<string, string> $headers
     */
    public function verifyWebhook(string $payload, array $headers = []): bool
    {
        // 测试用简化签名：payload 必须等于约定的「已签名密文」
        return $payload === 'SIGNED_EVENT_PAYLOAD';
    }

    /**
     * @return array<string, mixed>
     */
    public function parseWebhook(string $payload): array
    {
        return [
            'gateway' => 'wechat',
            'event_id' => 'EV_1',
            'event_type' => 'refund.success',
            'data' => ['raw' => $payload],
            'raw' => $payload,
        ];
    }

    public static function getName(): string
    {
        return 'wechat';
    }
}
