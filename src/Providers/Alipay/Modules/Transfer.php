<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;

/**
 * 支付宝转账模块
 */
readonly class Transfer
{
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
            'out_biz_no'      => $params['out_biz_no'],
            'trans_amount'    => $params['trans_amount'],
            'product_code'    => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene'       => 'DIRECT_TRANSFER',
            'order_title'     => $params['order_title'] ?? '转账',
            'payee_info'      => [
                'identity'     => $params['payee_account'],
                'identity_type' => 'ALIPAY_LOGON_ID',
                'name'         => $params['payee_name'] ?? '',
            ],
        ], $params);

        $payload = $this->buildParams('alipay.fund.trans.uni.transfer', $biz);
        $response = $this->app->http()->post(
            $this->app->config()->get('gateway'),
            ['form_params' => $payload]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询转账订单
     *
     * @return array<string, mixed>
     */
    public function query(string $outBizNo): array
    {
        $payload = $this->buildParams('alipay.fund.trans.common.query', [
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene'    => 'DIRECT_TRANSFER',
            'out_biz_no'   => $outBizNo,
        ]);
        $response = $this->app->http()->post(
            $this->app->config()->get('gateway'),
            ['form_params' => $payload]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 构造请求参数
     *
     * @param array<string, mixed> $biz
     * @return array<string, mixed>
     */
    private function buildParams(string $method, array $biz): array
    {
        $config = $this->app->config();
        $params = [
            'app_id'      => $config->appId(),
            'method'      => $method,
            'format'      => 'JSON',
            'charset'     => 'utf-8',
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'biz_content' => json_encode($biz),
        ];

        $params['sign'] = $this->sign($params, $config->get('private_key'));

        return $params;
    }

    /**
     * RSA2 签名
     *
     * @param array<string, mixed> $params
     */
    private function sign(array $params, string $privateKey): string
    {
        ksort($params);
        $string = http_build_query($params);
        $string = urldecode($string);

        $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
               wordwrap($privateKey, 64, "\n", true) .
               "\n-----END RSA PRIVATE KEY-----";

        openssl_sign($string, $sign, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($sign);
    }
}
