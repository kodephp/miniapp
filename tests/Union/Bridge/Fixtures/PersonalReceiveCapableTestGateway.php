<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Core\AbstractGateway;

/**
 * 提供个人收款方法的最小支付网关，用于验证个人收款委托链路。
 *
 * 与 {@see RefundCapableTestGateway} 同理：以「方法存在」形式模拟一个支持
 * PersonalReceiveCapableInterface 的网关（kode/pays 2.3.0 真实微信 / 支付宝 / Stripe 网关已
 * implements 该接口，但此处用夹具隔离网络依赖），与 miniapp 桥接的 method_exists 守卫一致。
 *
 * @internal 仅测试使用
 */
final class PersonalReceiveCapableTestGateway extends AbstractGateway
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
    public function queryRefund(string $refundId): array
    {
        return ['refund_id' => $refundId];
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
     * 生成个人收款二维码（模拟 kode/pays 网关 createQrCode）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createQrCode(array $params): array
    {
        return array_merge(['_method' => 'createQrCode'], $params);
    }

    /**
     * 查询个人收款记录（模拟 kode/pays 网关 queryRecords）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function queryRecords(array $params): array
    {
        return array_merge(['_method' => 'queryRecords'], $params);
    }

    /**
     * 提现到银行卡（模拟 kode/pays 网关 withdraw）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function withdraw(array $params): array
    {
        return array_merge(['_method' => 'withdraw'], $params);
    }

    /**
     * 查询提现结果（模拟 kode/pays 网关 queryWithdraw）
     *
     * @return array<string, mixed>
     */
    public function queryWithdraw(string $outBizNo): array
    {
        return ['out_biz_no' => $outBizNo, '_method' => 'queryWithdraw'];
    }

    public static function getName(): string
    {
        return 'wechat';
    }
}
