<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Douyin\Modules;

use Kode\MiniApp\Providers\Douyin\DouyinApp;

/**
 * 抖音登录/授权模块
 */
readonly class Auth
{
    private const string BASE_URL = 'https://developer.toutiao.com/api/apps';

    public function __construct(
        private DouyinApp $app,
    ) {
    }

    /**
     * 小程序登录，获取 session
     *
     * @return array<string, mixed>
     */
    public function session(string $code, string $anonymousCode = ''): array
    {
        $config = $this->app->config();
        $params = [
            'appid'            => $config->appId(),
            'secret'           => $config->secret(),
            'code'             => $code,
            'anonymous_code'   => $anonymousCode,
        ];

        $response = $this->app->http()->get(self::BASE_URL . '/v2/jscode2session', ['query' => $params]);
        $result   = json_decode((string) $response->getBody(), true);

        if (($result['err_no'] ?? 0) !== 0) {
            throw new \RuntimeException("抖音登录失败: [{$result['err_no']}] {$result['err_tips']}");
        }

        return $result['data'] ?? [];
    }

    /**
     * 获取 AccessToken
     */
    public function token(): string
    {
        $config = $this->app->config();
        $params = [
            'appid'      => $config->appId(),
            'secret'     => $config->secret(),
            'grant_type' => 'client_credential',
        ];

        $response = $this->app->http()->get(self::BASE_URL . '/v2/token', ['query' => $params]);
        $result   = json_decode((string) $response->getBody(), true);

        if (($result['err_no'] ?? 0) !== 0) {
            throw new \RuntimeException("获取 AccessToken 失败: [{$result['err_no']}] {$result['err_tips']}");
        }

        return (string) ($result['data']['access_token'] ?? '');
    }
}
