<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书通讯录模块
 */
readonly class Contact
{
    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 获取部门列表
     *
     * @return array<string, mixed>
     */
    public function departments(?string $parentId = null): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url');
        $params   = $parentId !== null ? ['parent_department_id' => $parentId] : [];
        $response = $this->app->http()->get(
            "{$baseUrl}/open-apis/contact/v3/departments",
            [
                'headers' => ['Authorization' => "Bearer {$token}"],
                'query'   => $params,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data']['items'] ?? [];
    }

    /**
     * 获取部门用户列表
     *
     * @return array<string, mixed>
     */
    public function departmentUsers(string $departmentId): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url');
        $response = $this->app->http()->get(
            "{$baseUrl}/open-apis/contact/v3/users",
            [
                'headers' => ['Authorization' => "Bearer {$token}"],
                'query'   => ['department_id' => $departmentId],
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data']['items'] ?? [];
    }

    /**
     * 获取用户信息
     *
     * @return array<string, mixed>
     */
    public function user(string $userId): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url');
        $response = $this->app->http()->get(
            "{$baseUrl}/open-apis/contact/v3/users/{$userId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data'] ?? [];
    }
}
