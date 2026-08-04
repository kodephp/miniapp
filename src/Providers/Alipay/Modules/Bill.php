<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Providers\Alipay\AlipayGateway;

/**
 * 支付宝账单模块
 */
readonly class Bill
{
    private const string METHOD_DOWNLOAD = 'alipay.data.dataservice.bill.downloadurl.query';

    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 查询账单下载地址
     *
     * @return array<string, mixed>
     */
    public function download(string $billType, string $billDate): array
    {
        return $this->app->gateway()
            ->execute(self::METHOD_DOWNLOAD, [
                'bill_type' => $billType,
                'bill_date' => $billDate,
            ])
            ->throwIfFailed('支付宝查询账单下载地址')
            ->array(AlipayGateway::responseNode(self::METHOD_DOWNLOAD));
    }
}
