<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu\Modules;

use Kode\MiniApp\Providers\Baidu\BaiduApp;

/**
 * 百度支付模块
 */
readonly class Pay
{
    private const BASE_URL = 'https://openapi.baidu.com';

    public function __construct(
        private BaiduApp $app,
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
        $token    = $this->app->auth()->token()['access_token'] ?? '';
        $payload  = array_merge([
            'access_token' => $token,
            'dealId'       => $this->app->config()->get('deal_id'),
            'appKey'       => $this->app->config()->appId(),
        ], $params);

        $response = $this->app->http()->postJson(self::BASE_URL . '/rest/2.0/smartapp/pay/polymer/precreate', $payload);

        return json_decode((string) $response->getBody(), true);
    }
}
