<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Providers\Alipay\AlipayGateway;

/**
 * 支付宝转账模块
 */
readonly class Transfer
{
    private const string METHOD_CREATE = 'alipay.fund.trans.uni.transfer';
    private const string METHOD_QUERY  = 'alipay.fund.trans.common.query';

    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 单笔转账
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $biz = array_merge([
            'out_biz_no'   => $params['out_biz_no'] ?? '',
            'trans_amount' => $params['trans_amount'] ?? '',
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene'    => 'DIRECT_TRANSFER',
            'order_title'  => $params['order_title'] ?? '转账',
            'payee_info'   => [
                'identity'      => $params['payee_account'] ?? '',
                'identity_type' => 'ALIPAY_LOGON_ID',
                'name'          => $params['payee_name'] ?? '',
            ],
        ], $params);

        return $this->app->gateway()
            ->execute(self::METHOD_CREATE, $biz)
            ->throwIfFailed('支付宝单笔转账')
            ->array(AlipayGateway::responseNode(self::METHOD_CREATE));
    }

    /**
     * 查询转账订单
     *
     * @return array<string, mixed>
     */
    public function query(string $outBizNo): array
    {
        return $this->app->gateway()
            ->execute(self::METHOD_QUERY, [
                'product_code' => 'TRANS_ACCOUNT_NO_PWD',
                'biz_scene'    => 'DIRECT_TRANSFER',
                'out_biz_no'   => $outBizNo,
            ])
            ->throwIfFailed('支付宝查询转账订单')
            ->array(AlipayGateway::responseNode(self::METHOD_QUERY));
    }
}
