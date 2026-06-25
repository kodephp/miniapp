<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Douyin\Modules;

use Kode\MiniApp\Providers\Douyin\DouyinApp;

/**
 * 抖音支付模块
 */
readonly class Pay
{
    private const BASE_URL = 'https://developer.toutiao.com/api/apps/ecpay/v1';

    public function __construct(
        private DouyinApp $app,
    ) {
    }

    /**
     * 创建订单
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $payload  = $this->buildPayload('create_order', $params);
        $response = $this->app->http()->postJson(self::BASE_URL . '/create_order', $payload);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询订单
     *
     * @return array<string, mixed>
     */
    public function query(string $outOrderNo): array
    {
        $payload  = $this->buildPayload('query_order', ['out_order_no' => $outOrderNo]);
        $response = $this->app->http()->postJson(self::BASE_URL . '/query_order', $payload);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 构造带签名的请求体
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function buildPayload(string $method, array $params): array
    {
        $config = $this->app->config();
        $data   = array_merge([
            'app_id'    => $config->appId(),
            'out_order_no' => $params['out_order_no'] ?? '',
            'total_amount' => $params['total_amount'] ?? 0,
            'subject'      => $params['subject'] ?? '',
            'body'         => $params['body'] ?? '',
            'valid_time'   => $params['valid_time'] ?? 300,
            'notify_url'   => $config->get('notify_url', ''),
            'sign'         => '',
            'salt'         => $config->get('salt', ''),
        ], $params);

        $data['sign'] = $this->sign($data, $config->get('token', ''));

        return $data;
    }

    /**
     * MD5 签名
     *
     * @param array<string, mixed> $params
     */
    private function sign(array $params, string $token): string
    {
        ksort($params);
        $string = http_build_query($params);
        $string = urldecode($string) . '&token=' . $token;

        return md5($string);
    }
}
