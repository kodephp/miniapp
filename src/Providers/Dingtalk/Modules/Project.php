<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉项目模块
 */
readonly class Project
{
    private const BASE_URL = 'https://oapi.dingtalk.com/topapi';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 创建项目
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/project/create?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("创建项目失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 更新项目
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/project/update?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("更新项目失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取项目详情
     *
     * @return array<string, mixed>
     */
    public function get(string $projectId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/project/get?access_token={$token}",
            ['project_id' => $projectId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取项目失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取项目列表
     *
     * @return array<string, mixed>
     */
    public function list(int $offset = 0, int $count = 20): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/project/list?access_token={$token}",
            [
                'offset' => $offset,
                'count'  => $count,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取项目列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 添加项目任务
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addTask(string $projectId, array $data): array
    {
        $token = $this->app->auth()->token();
        $data['project_id'] = $projectId;
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/project/task/create?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("添加任务失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取项目任务列表
     *
     * @return array<string, mixed>
     */
    public function taskList(string $projectId, int $offset = 0, int $count = 20): array
    {
        $token = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/project/task/list?access_token={$token}",
            [
                'project_id' => $projectId,
                'offset'     => $offset,
                'count'      => $count,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取任务列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
