<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉智能工作流模块
 */
readonly class Workflow
{
    private const BASE_URL = 'https://oapi.dingtalk.com/topapi';

    public function __construct(
        private DingtalkApp $app,
    ) {
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
            self::BASE_URL . "/processinstance/create?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("创建审批实例失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取审批实例详情
     *
     * @return array<string, mixed>
     */
    public function getInstance(string $processInstanceId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/processinstance/get?access_token={$token}",
            ['process_instance_id' => $processInstanceId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取审批实例失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取审批模板列表
     *
     * @return array<string, mixed>
     */
    public function templateList(int $offset = 0, int $size = 10): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/processtemplate/listuser?access_token={$token}",
            [
                'offset' => $offset,
                'size'   => $size,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取模板列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取审批实例列表
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function instanceList(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/processinstance/listids?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取实例列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 撤销审批实例
     *
     * @return array<string, mixed>
     */
    public function terminateInstance(string $processInstanceId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/processinstance/terminate?access_token={$token}",
            ['process_instance_id' => $processInstanceId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("撤销审批实例失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
