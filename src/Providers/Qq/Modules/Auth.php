<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Qq\Modules;

use Kode\MiniApp\Providers\Qq\QqApp;

/**
 * QQ 登录/授权模块
 */
readonly class Auth
{
    private const BASE_URL = 'https://api.q.qq.com';

    public function __construct(
        private QqApp $app,
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

        $response = $this->app->http()->get(self::BASE_URL . '/sns/jscode2session', ['query' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("QQ 登录失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取 AccessToken
     */
    public function token(): string
    {
        $config = $this->app->config();
        $params = [
            'grant_type' => 'client_credential',
            'appid'      => $config->appId(),
            'secret'     => $config->secret(),
        ];

        $response = $this->app->http()->get(self::BASE_URL . '/cgi-bin/token', ['query' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取 AccessToken 失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return (string) $data['access_token'];
    }
}
