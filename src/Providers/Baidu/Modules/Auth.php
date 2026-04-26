<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu\Modules;

use Kode\MiniApp\Providers\Baidu\BaiduApp;

/**
 * 百度登录/授权模块
 */
readonly class Auth
{
    private const BASE_URL = 'https://openapi.baidu.com';

    public function __construct(
        private BaiduApp $app,
    ) {
    }

    /**
     * 获取 SessionKey（小程序登录）
     *
     * @return array<string, mixed>
     */
    public function session(string $code): array
    {
        $config = $this->app->config();
        $params = [
            'client_id'     => $config->appId(),
            'sk'            => $config->secret(),
            'code'          => $code,
        ];

        $response = $this->app->http()->get(self::BASE_URL . '/oauth/2.0/token', ['query' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        if (isset($data['error'])) {
            throw new \RuntimeException("百度登录失败: [{$data['error']}] {$data['error_description']}");
        }

        return $data;
    }

    /**
     * 获取 AccessToken（服务端）
     *
     * @return array<string, mixed>
     */
    public function token(): array
    {
        $config = $this->app->config();
        $params = [
            'grant_type'    => 'client_credentials',
            'client_id'     => $config->appId(),
            'client_secret' => $config->secret(),
            'scope'         => 'basic',
        ];

        $response = $this->app->http()->get(self::BASE_URL . '/oauth/2.0/token', ['query' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        if (isset($data['error'])) {
            throw new \RuntimeException("获取 AccessToken 失败: [{$data['error']}] {$data['error_description']}");
        }

        return $data;
    }
}
