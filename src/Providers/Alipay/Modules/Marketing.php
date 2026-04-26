<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;

/**
 * 支付宝营销模块
 */
readonly class Marketing
{
    private const BASE_URL = 'https://openapi.alipay.com/gateway.do';

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
        $response = $this->request('alipay.marketing.campaign.cash.create', $data);

        return $response['alipay_marketing_campaign_cash_create_response'] ?? [];
    }

    /**
     * 触发红包
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function triggerCash(array $data): array
    {
        $response = $this->request('alipay.marketing.campaign.cash.trigger', $data);

        return $response['alipay_marketing_campaign_cash_trigger_response'] ?? [];
    }

    /**
     * 创建优惠券模板
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createVoucherTemplate(array $data): array
    {
        $response = $this->request('alipay.marketing.voucher.templatelist.create', $data);

        return $response['alipay_marketing_voucher_templatelist_create_response'] ?? [];
    }

    /**
     * 发送优惠券
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendVoucher(array $data): array
    {
        $response = $this->request('alipay.marketing.voucher.send', $data);

        return $response['alipay_marketing_voucher_send_response'] ?? [];
    }

    /**
     * 统一收单线下交易预创建（扫码支付）
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function precreate(array $data): array
    {
        $response = $this->request('alipay.trade.precreate', $data);

        return $response['alipay_trade_precreate_response'] ?? [];
    }

    /**
     * 统一收单交易退款
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function refund(array $data): array
    {
        $response = $this->request('alipay.trade.refund', $data);

        return $response['alipay_trade_refund_response'] ?? [];
    }

    /**
     * 发送通用请求
     *
     * @param array<string, mixed> $bizContent
     * @return array<string, mixed>
     */
    private function request(string $method, array $bizContent): array
    {
        $config   = $this->app->config();
        $params   = [
            'app_id'     => $config->appId(),
            'method'     => $method,
            'format'     => 'JSON',
            'charset'    => 'utf-8',
            'sign_type'  => 'RSA2',
            'timestamp'  => date('Y-m-d H:i:s'),
            'version'    => '1.0',
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];

        $params['sign'] = $this->sign($params, $config->get('private_key', ''));

        $response = $this->app->http()->post(self::BASE_URL, $params);

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * RSA2 签名
     *
     * @param array<string, mixed> $params
     */
    private function sign(array $params, string $privateKey): string
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null && $k !== 'sign') {
                $string .= "{$k}={$v}&";
            }
        }
        $string = rtrim($string, '&');

        $key = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($privateKey, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
        openssl_sign($string, $sign, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($sign);
    }
}
