<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信登录模块
 */
readonly class Auth
{
    private const SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 小程序登录，获取 session
     *
     * @return array<string, mixed>
     */
    public function session(string $code): array
    {
        $config = $this->app->config();
        $params = [
            'appid'      => $config->appId(),
            'secret'     => $config->secret(),
            'js_code'    => $code,
            'grant_type' => 'authorization_code',
        ];

        $response = $this->app->http()->get(self::SESSION_URL, ['query' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("微信登录失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取 AccessToken
     */
    public function token(): string
    {
        $config = $this->app->config();
        $url    = 'https://api.weixin.qq.com/cgi-bin/token';
        $params = [
            'grant_type' => 'client_credential',
            'appid'      => $config->appId(),
            'secret'     => $config->secret(),
        ];

        $response = $this->app->http()->get($url, ['query' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取 AccessToken 失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return (string) $data['access_token'];
    }
}
