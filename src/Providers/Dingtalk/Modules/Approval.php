<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉审批模块
 */
readonly class Approval
{
    private const string BASE_URL = 'https://oapi.dingtalk.com';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 获取审批实例
     *
     * @return array<string, mixed>
     */
    public function instance(string $processInstanceId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/topapi/processinstance/get?access_token={$token}",
            ['process_instance_id' => $processInstanceId]
        );
        $data = json_decode((string) $response->getBody(), true);

        if ($data['errcode'] !== 0) {
            throw new \RuntimeException("获取审批实例失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
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
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/topapi/processinstance/create?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }
}
