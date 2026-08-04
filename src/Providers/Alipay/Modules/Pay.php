<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Providers\Alipay\AlipayGateway;

/**
 * 支付宝支付模块
 */
readonly class Pay
{
    private const string METHOD_CREATE = 'alipay.trade.create';

    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 创建订单（小程序支付）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $biz = array_merge([
            'out_trade_no' => $params['out_trade_no'] ?? '',
            'total_amount' => $params['total_amount'] ?? '',
            'subject'      => $params['subject'] ?? '',
            'product_code' => 'QUICK_MSECURITY_PAY',
        ], $params);

        return $this->app->gateway()
            ->execute(self::METHOD_CREATE, $biz)
            ->throwIfFailed('支付宝创建支付订单')
            ->array(AlipayGateway::responseNode(self::METHOD_CREATE));
    }
}
