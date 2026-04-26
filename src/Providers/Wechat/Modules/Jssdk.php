<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Utils\Str;

/**
 * 微信 JS-SDK 模块
 */
readonly class Jssdk
{
    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 获取 JS-SDK 配置
     *
     * @return array<string, mixed>
     */
    public function config(string $url, array $apis = []): array
    {
        $timestamp = (string) time();
        $nonceStr  = Str::random(16);
        $ticket    = $this->ticket();

        $data = [
            'jsapi_ticket' => $ticket,
            'noncestr'     => $nonceStr,
            'timestamp'    => $timestamp,
            'url'          => $url,
        ];
        ksort($data);
        $string = http_build_query($data);
        $sign   = sha1(urldecode($string));

        return [
            'appId'     => $this->app->config()->appId(),
            'timestamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'signature' => $sign,
            'jsApiList' => $apis,
        ];
    }

    /**
     * 获取 jsapi_ticket
     */
    private function ticket(): string
    {
        $token    = $this->app->auth()->token();
        $url      = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$token}&type=jsapi";
        $response = $this->app->http()->get($url);
        $data     = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取 jsapi_ticket 失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return (string) ($data['ticket'] ?? '');
    }
}
