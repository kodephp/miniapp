<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Core\AbstractGateway;

/**
 * 实现加密货币能力的最小支付网关，用于验证加密货币委托链路。
 *
 * 以「方法存在」形式模拟一个支持 CryptoCapableInterface 的网关（kode/pays 2.3.0 真实
 * CoinbaseGateway 已 implements 该接口，但此处用夹具隔离网络依赖），与 miniapp 桥接的
 * method_exists 守卫一致。
 *
 * @internal 仅测试使用
 */
final class CryptoCapableTestGateway extends AbstractGateway
{
    protected function getBaseUrl(): string
    {
        return 'https://api.commerce.coinbase.com/';
    }

    protected function parseResponse(string $response): array
    {
        return ['raw' => $response];
    }

    #[\Override]
    public function createOrder(array $params): array
    {
        return array_merge(['_method' => 'createOrder'], $params);
    }

    #[\Override]
    public function queryOrder(string $orderId): array
    {
        return ['order_id' => $orderId, '_method' => 'queryOrder'];
    }

    #[\Override]
    public function queryRefund(string $refundId): array
    {
        return ['refund_id' => $refundId];
    }

    #[\Override]
    public function refund(array $params): array
    {
        return array_merge(['_method' => 'refund'], $params);
    }

    #[\Override]
    public function verifyNotify(array $data): bool
    {
        return ($data['signature'] ?? null) === 'valid';
    }

    #[\Override]
    public function closeOrder(string $orderId): array
    {
        return ['order_id' => $orderId];
    }

    /**
     * 创建指定加密货币定价的订单（模拟 kode/pays 网关 createCryptoOrder）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createCryptoOrder(array $params): array
    {
        return array_merge(['_method' => 'createCryptoOrder'], $params);
    }

    /**
     * 获取加密货币支付地址（模拟 kode/pays 网关 getPaymentAddresses）
     *
     * @return array<string, mixed>
     */
    public function getPaymentAddresses(string $orderId): array
    {
        return ['order_id' => $orderId, '_method' => 'getPaymentAddresses'];
    }

    /**
     * 查询链上确认状态（模拟 kode/pays 网关 getConfirmations）
     *
     * @return array<string, mixed>
     */
    public function getConfirmations(string $orderId): array
    {
        return ['order_id' => $orderId, '_method' => 'getConfirmations'];
    }

    /**
     * 查询加密货币实时汇率（模拟 kode/pays 网关 getExchangeRate）
     *
     * @return array<string, mixed>
     */
    public function getExchangeRate(string $cryptoCurrency, string $fiatCurrency = 'USD'): array
    {
        return [
            'crypto' => $cryptoCurrency,
            'fiat' => $fiatCurrency,
            '_method' => 'getExchangeRate',
        ];
    }

    public static function getName(): string
    {
        return 'coinbase';
    }
}
