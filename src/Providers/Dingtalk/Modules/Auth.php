<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉认证模块
 */
readonly class Auth
{
    private const BASE_URL = 'https://oapi.dingtalk.com';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 获取 AccessToken
     */
    public function token(): string
    {
        $config = $this->app->config();
        $params = [
            'appkey'    => $config->appKey(),
            'appsecret' => $config->appSecret(),
        ];

        $response = $this->app->http()->get(self::BASE_URL . '/gettoken', ['query' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取 AccessToken 失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return (string) $data['access_token'];
    }

    /**
     * 通过 code 获取用户信息
     *
     * @return array<string, mixed>
     */
    public function user(string $code): array
    {
        $token    = $this->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/user/getuserinfo?access_token={$token}",
            ['code' => $code]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取用户信息失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取用户详情（通过 user_id）
     *
     * @return array<string, mixed>
     */
    public function userDetail(string $userId): array
    {
        $token    = $this->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/topapi/v2/user/get?access_token={$token}",
            ['userid' => $userId]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取用户详情失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data['result'] ?? [];
    }
}
