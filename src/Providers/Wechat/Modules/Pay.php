<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信支付模块（V3 版）
 */
readonly class Pay
{
    private const string BASE_URL = 'https://api.mch.weixin.qq.com/v3';

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
        $mchId    = $this->app->config()->get('mch_id');
        $url      = self::BASE_URL . "/pay/transactions/out-trade-no/{$outTradeNo}?mchid={$mchId}";
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
            'mchid'        => $this->app->config()->get('mch_id'),
            'notify_url'   => $this->app->config()->get('notify_url'),
        ], $params);
    }
}
