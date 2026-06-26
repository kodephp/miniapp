<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信支付模块（V3 版）
 */
readonly class Pay
{
    private const BASE_URL = 'https://api.mch.weixin.qq.com/v3';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 统一下单（JSAPI）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function order(array $params): array
    {
        $url      = self::BASE_URL . '/pay/transactions/jsapi';
        $response = $this->app->http()->postJson($url, $this->buildPayload($params));

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询订单
     *
     * @return array<string, mixed>
     */
    public function query(string $outTradeNo): array
    {
        $mchId    = $this->app->config()->mchId();
        $url      = self::BASE_URL . "/pay/transactions/out-trade-no/{$outTradeNo}?mchid={$mchId}";
        $response = $this->app->http()->get($url);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 关闭订单
     *
     * @return array<string, mixed>
     */
    public function close(string $outTradeNo): array
    {
        $mchId    = $this->app->config()->mchId();
        $url      = self::BASE_URL . "/pay/transactions/out-trade-no/{$outTradeNo}/close";
        $response = $this->app->http()->postJson($url, ['mchid' => $mchId]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 申请退款
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function refund(array $params): array
    {
        $url      = self::BASE_URL . '/refund/domestic/refunds';
        $response = $this->app->http()->postJson($url, $params);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询退款
     *
     * @return array<string, mixed>
     */
    public function queryRefund(string $outRefundNo): array
    {
        $url      = self::BASE_URL . "/refund/domestic/refunds/{$outRefundNo}";
        $response = $this->app->http()->get($url);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 申请交易账单
     *
     * @return array<string, mixed>
     */
    public function tradeBill(string $billDate, string $billType = 'ALL'): array
    {
        $url      = self::BASE_URL . "/bill/tradebill?bill_date={$billDate}&bill_type={$billType}";
        $response = $this->app->http()->get($url);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 申请资金账单
     *
     * @return array<string, mixed>
     */
    public function fundBill(string $billDate, string $accountType = 'BASIC'): array
    {
        $url      = self::BASE_URL . "/bill/fundflowbill?bill_date={$billDate}&account_type={$accountType}";
        $response = $this->app->http()->get($url);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 构造请求体
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function buildPayload(array $params): array
    {
        return array_merge([
            'appid'        => $this->app->config()->appId(),
            'mchid'        => $this->app->config()->mchId(),
            'notify_url'   => $this->app->config()->get('notify_url', ''),
        ], $params);
    }
}
