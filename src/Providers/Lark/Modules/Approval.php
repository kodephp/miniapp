<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书审批模块
 */
readonly class Approval
{
    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 创建审批实例
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url');
        $response = $this->app->http()->postJson(
            "{$baseUrl}/open-apis/approval/v4/instances",
            $data,
            ['Authorization' => "Bearer {$token}"]
        );
        $result = json_decode((string) $response->getBody(), true);

        return $result['data'] ?? [];
    }

    /**
     * 查询审批实例
     *
     * @return array<string, mixed>
     */
    public function instance(string $instanceCode): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url');
        $response = $this->app->http()->get(
            "{$baseUrl}/open-apis/approval/v4/instances/{$instanceCode}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );
        $result = json_decode((string) $response->getBody(), true);

        return $result['data'] ?? [];
    }
}
