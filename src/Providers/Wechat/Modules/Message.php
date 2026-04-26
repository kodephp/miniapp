<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信消息模块（公众号/小程序消息推送）
 */
readonly class Message
{
    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 校验服务器签名（接入时）
     */
    public function verify(string $signature, string $timestamp, string $nonce, string $token = ''): bool
    {
        $token = $token ?: $this->app->config()->get('token', '');
        $tmp   = [$token, $timestamp, $nonce];
        sort($tmp, SORT_STRING);

        return sha1(implode('', $tmp)) === $signature;
    }

    /**
     * 发送订阅消息（小程序）
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendSubscribe(string $openid, string $templateId, array $data, string $page = ''): array
    {
        $token    = $this->app->auth()->token();
        $url      = "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token={$token}";
        $payload  = [
            'touser'      => $openid,
            'template_id' => $templateId,
            'page'        => $page,
            'data'        => $data,
        ];

        $response = $this->app->http()->postJson($url, $payload);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 发送模板消息（公众号）
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendTemplate(string $openid, string $templateId, string $url = '', array $data = [], string $miniProgram = ''): array
    {
        $token    = $this->app->auth()->token();
        $apiUrl   = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$token}";
        $payload  = [
            'touser'      => $openid,
            'template_id' => $templateId,
            'url'         => $url,
            'data'        => $data,
        ];

        if (!empty($miniProgram)) {
            $payload['miniprogram'] = ['appid' => $miniProgram];
        }

        $response = $this->app->http()->postJson($apiUrl, $payload);

        return json_decode((string) $response->getBody(), true);
    }
}
