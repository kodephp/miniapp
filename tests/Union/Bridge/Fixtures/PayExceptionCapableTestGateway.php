<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Contract\TransferCapableInterface;
use Kode\Pays\Core\AbstractGateway;
use Kode\Pays\Core\PayException;

/**
 * 用于验证「桥接层把 kode/pays 网关抛出的 PayException 归一化为本包 ApiException」的夹具网关。
 *
 * 实现 TransferCapableInterface，但 singleTransfer 故意抛出真实 PayException（含网关原始错误码），
 * 以验证桥接层 invokeGateway 归一化出口是否生效。其余方法返回占位数组，不参与断言。
 *
 * @internal 仅测试使用
 */
final class PayExceptionCapableTestGateway extends AbstractGateway implements TransferCapableInterface
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

    #[\Override]
    public function singleTransfer(array $params): array
    {
        // 模拟「网关余额不足」业务错误：携带网关原始错误码 / 信息
        throw PayException::gatewayError(
            '微信转账余额不足',
            'INSUFFICIENT_BALANCE',
            '余额不足',
        );
    }

    #[\Override]
    public function batchTransfer(array $params): array
    {
        return $params;
    }

    #[\Override]
    public function queryTransfer(string $outBizNo): array
    {
        return ['out_biz_no' => $outBizNo];
    }

    #[\Override]
    public function transferReceipt(string $outBizNo): array
    {
        return ['out_biz_no' => $outBizNo];
    }

    public static function getName(): string
    {
        return 'wechat';
    }
}
