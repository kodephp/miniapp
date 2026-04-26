<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书知识库模块
 */
readonly class Wiki
{
    private const BASE_URL = 'https://open.feishu.cn/open-apis';

    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 获取知识库列表
     *
     * @return array<string, mixed>
     */
    public function list(int $pageSize = 20, string $pageToken = ''): array
    {
        $token = $this->app->auth()->token();
        $url   = self::BASE_URL . "/wiki/v2/spaces?page_size={$pageSize}";
        if (!empty($pageToken)) {
            $url .= "&page_token={$pageToken}";
        }
        $response = $this->app->http()->get(
            $url,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取知识库详情
     *
     * @return array<string, mixed>
     */
    public function get(string $spaceId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/wiki/v2/spaces/{$spaceId}",
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取知识库节点列表
     *
     * @return array<string, mixed>
     */
    public function nodes(string $spaceId, string $parentNodeToken = '', int $pageSize = 20): array
    {
        $token = $this->app->auth()->token();
        $url   = self::BASE_URL . "/wiki/v2/spaces/{$spaceId}/nodes?page_size={$pageSize}";
        if (!empty($parentNodeToken)) {
            $url .= "&parent_node_token={$parentNodeToken}";
        }
        $response = $this->app->http()->get(
            $url,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 创建知识库节点
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createNode(string $spaceId, array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/wiki/v2/spaces/{$spaceId}/nodes",
            $params,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
