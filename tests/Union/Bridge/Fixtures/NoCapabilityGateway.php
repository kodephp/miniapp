<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Core\AbstractGateway;

/**
 * 最小支付网关（仅实现核心接口，未实现任何能力接口），用于验证 method_exists 守卫。
 *
 * @internal 仅测试使用
 */
final class NoCapabilityGateway extends AbstractGateway
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

    public static function getName(): string
    {
        return 'wechat';
    }
}
