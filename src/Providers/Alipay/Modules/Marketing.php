<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Providers\Alipay\AlipayGateway;

/**
 * 支付宝营销模块
 */
readonly class Marketing
{
    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 创建现金活动
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createCashActivity(array $data): array
    {
        return $this->request('alipay.marketing.campaign.cash.create', $data);
    }

    /**
     * 触发红包
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function triggerCash(array $data): array
    {
        return $this->request('alipay.marketing.campaign.cash.trigger', $data);
    }

    /**
     * 创建优惠券模板
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createVoucherTemplate(array $data): array
    {
        return $this->request('alipay.marketing.voucher.templatelist.create', $data);
    }

    /**
     * 发送优惠券
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendVoucher(array $data): array
    {
        return $this->request('alipay.marketing.voucher.send', $data);
    }

    /**
     * 统一收单线下交易预创建（扫码支付）
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function precreate(array $data): array
    {
        return $this->request('alipay.trade.precreate', $data);
    }

    /**
     * 统一收单交易退款
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function refund(array $data): array
    {
        return $this->request('alipay.trade.refund', $data);
    }

    /**
     * 统一收单交易查询
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function query(array $data): array
    {
        return $this->request('alipay.trade.query', $data);
    }

    /**
     * 统一收单交易关闭
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function close(array $data): array
    {
        return $this->request('alipay.trade.close', $data);
    }

    /**
     * 发送通用请求并归一化提取响应节点
     *
     * @param array<string, mixed> $bizContent
     * @return array<string, mixed>
     */
    private function request(string $method, array $bizContent): array
    {
        return $this->app->gateway()
            ->execute($method, $bizContent)
            ->throwIfFailed('支付宝营销接口调用')
            ->array(AlipayGateway::responseNode($method));
    }
}
