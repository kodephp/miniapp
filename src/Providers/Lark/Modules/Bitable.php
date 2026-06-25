<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书多维表格模块
 */
readonly class Bitable
{
    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 获取多维表格元数据
     *
     * @return array<string, mixed>
     */
    public function meta(string $appToken): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url', 'https://open.feishu.cn');
        $response = $this->app->http()->get(
            "{$baseUrl}/open-apis/bitable/v1/apps/{$appToken}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data'] ?? [];
    }

    /**
     * 列出多维表格的数据表
     *
     * @return array<string, mixed>
     */
    public function tables(string $appToken, string $pageToken = '', int $pageSize = 20): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url', 'https://open.feishu.cn');
        $params   = ['page_size' => $pageSize];
        if (!empty($pageToken)) {
            $params['page_token'] = $pageToken;
        }
        $response = $this->app->http()->get(
            "{$baseUrl}/open-apis/bitable/v1/apps/{$appToken}/tables",
            [
                'headers' => ['Authorization' => "Bearer {$token}"],
                'query'   => $params,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data'] ?? [];
    }

    /**
     * 新增记录
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function createRecord(string $appToken, string $tableId, array $fields): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url', 'https://open.feishu.cn');
        $response = $this->app->http()->post(
            "{$baseUrl}/open-apis/bitable/v1/apps/{$appToken}/tables/{$tableId}/records",
            [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'json'    => ['fields' => $fields],
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data'] ?? [];
    }

    /**
     * 查询记录
     *
     * @param array<string, mixed> $filter
     * @return array<string, mixed>
     */
    public function records(
        string $appToken,
        string $tableId,
        array $filter = [],
        string $pageToken = '',
        int $pageSize = 500
    ): array {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url', 'https://open.feishu.cn');
        $params   = ['page_size' => $pageSize];
        if (!empty($pageToken)) {
            $params['page_token'] = $pageToken;
        }
        if (!empty($filter)) {
            $params['filter'] = json_encode($filter);
        }
        $response = $this->app->http()->get(
            "{$baseUrl}/open-apis/bitable/v1/apps/{$appToken}/tables/{$tableId}/records",
            [
                'headers' => ['Authorization' => "Bearer {$token}"],
                'query'   => $params,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data'] ?? [];
    }

    /**
     * 更新记录
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function updateRecord(string $appToken, string $tableId, string $recordId, array $fields): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url', 'https://open.feishu.cn');
        $response = $this->app->http()->put(
            "{$baseUrl}/open-apis/bitable/v1/apps/{$appToken}/tables/{$tableId}/records/{$recordId}",
            [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'json'    => ['fields' => $fields],
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data'] ?? [];
    }

    /**
     * 删除记录
     *
     * @return array<string, mixed>
     */
    public function deleteRecord(string $appToken, string $tableId, string $recordId): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url', 'https://open.feishu.cn');
        $response = $this->app->http()->delete(
            "{$baseUrl}/open-apis/bitable/v1/apps/{$appToken}/tables/{$tableId}/records/{$recordId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data ?? [];
    }
}
