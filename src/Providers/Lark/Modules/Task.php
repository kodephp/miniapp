<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书任务模块
 */
readonly class Task
{
    private const string BASE_URL = 'https://open.feishu.cn/open-apis';

    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 创建任务
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . '/task/v2/tasks',
            $params,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取任务详情
     *
     * @return array<string, mixed>
     */
    public function get(string $taskGuid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/task/v2/tasks/{$taskGuid}",
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 更新任务
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(string $taskGuid, array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->patch(
            self::BASE_URL . "/task/v2/tasks/{$taskGuid}",
            $params,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除任务
     *
     * @return array<string, mixed>
     */
    public function delete(string $taskGuid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->delete(
            self::BASE_URL . "/task/v2/tasks/{$taskGuid}",
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 完成任务
     *
     * @return array<string, mixed>
     */
    public function complete(string $taskGuid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/task/v2/tasks/{$taskGuid}/complete",
            [],
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 取消完成任务
     *
     * @return array<string, mixed>
     */
    public function uncomplete(string $taskGuid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/task/v2/tasks/{$taskGuid}/uncomplete",
            [],
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
