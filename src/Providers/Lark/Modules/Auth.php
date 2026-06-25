<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书认证模块
 */
readonly class Auth
{
    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 获取 Tenant Access Token
     */
    public function token(): string
    {
        $config   = $this->app->config();
        $baseUrl  = $config->get('base_url', 'https://open.feishu.cn');
        $response = $this->app->http()->postJson(
            "{$baseUrl}/open-apis/auth/v3/tenant_access_token/internal",
            [
                'app_id'     => $config->appId(),
                'app_secret' => $config->secret(),
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (($data['code'] ?? 0) !== 0) {
            throw new \RuntimeException("获取 AccessToken 失败: [{$data['code']}] {$data['msg']}");
        }

        return (string) $data['tenant_access_token'];
    }

    /**
     * 通过 code 获取用户信息
     *
     * @return array<string, mixed>
     */
    public function user(string $code): array
    {
        $token    = $this->token();
        $baseUrl  = $this->app->config()->get('base_url', 'https://open.feishu.cn');
        $response = $this->app->http()->postJson(
            "{$baseUrl}/open-apis/authen/v1/access_token",
            ['grant_type' => 'authorization_code', 'code' => $code],
            ['Authorization' => "Bearer {$token}"]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (($data['code'] ?? 0) !== 0) {
            throw new \RuntimeException("获取用户信息失败: [{$data['code']}] {$data['msg']}");
        }

        return $data['data'] ?? [];
    }

    /**
     * 获取用户详情（通过 user_id）
     *
     * @return array<string, mixed>
     */
    public function userDetail(string $userId): array
    {
        $token    = $this->token();
        $baseUrl  = $this->app->config()->get('base_url', 'https://open.feishu.cn');
        $response = $this->app->http()->get(
            "{$baseUrl}/open-apis/contact/v3/users/{$userId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (($data['code'] ?? 0) !== 0) {
            throw new \RuntimeException("获取用户详情失败: [{$data['code']}] {$data['msg']}");
        }

        return $data['data'] ?? [];
    }
}
