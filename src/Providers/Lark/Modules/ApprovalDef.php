<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书审批定义模块（审批流程配置）
 */
readonly class ApprovalDef
{
    private const BASE_URL = 'https://open.feishu.cn/open-apis/approval/openapi/v2';

    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 获取审批定义列表
     *
     * @return array<string, mixed>
     */
    public function list(int $pageSize = 100, string $pageToken = ''): array
    {
        $token = $this->app->auth()->token();
        $url   = self::BASE_URL . '/approval/definitions?page_size=' . $pageSize;
        if (!empty($pageToken)) {
            $url .= '&page_token=' . $pageToken;
        }
        $response = $this->app->http()->get(
            $url,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取审批定义详情
     *
     * @return array<string, mixed>
     */
    public function get(string $approvalCode): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/approval/definitions/{$approvalCode}",
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 创建审批实例
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createInstance(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . '/instance',
            $data,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取审批实例列表
     *
     * @return array<string, mixed>
     */
    public function instanceList(string $approvalCode, int $pageSize = 100, string $pageToken = ''): array
    {
        $token = $this->app->auth()->token();
        $url   = self::BASE_URL . "/instance/list?approval_code={$approvalCode}&page_size={$pageSize}";
        if (!empty($pageToken)) {
            $url .= '&page_token=' . $pageToken;
        }
        $response = $this->app->http()->get(
            $url,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 审批任务同意
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function approve(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . '/task/approve',
            $data,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 审批任务拒绝
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function reject(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . '/task/reject',
            $data,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 审批任务转交
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function transfer(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . '/task/transfer',
            $data,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
