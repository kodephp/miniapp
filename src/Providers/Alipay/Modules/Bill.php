<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;

/**
 * 支付宝账单模块
 */
readonly class Bill
{
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
        $payload = $this->buildParams('alipay.data.dataservice.bill.downloadurl.query', [
            'bill_type' => $billType,
            'bill_date' => $billDate,
        ]);
        $response = $this->app->http()->post(
            $this->app->config()->get('gateway', 'https://openapi.alipay.com/gateway.do'),
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

        $params['sign'] = $this->sign($params, $config->get('private_key', ''));

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
