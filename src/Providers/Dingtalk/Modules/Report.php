<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉日志模块
 */
readonly class Report
{
    private const BASE_URL = 'https://oapi.dingtalk.com/topapi';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 获取用户日志列表
     *
     * @return array<string, mixed>
     */
    public function list(
        string $startTime,
        string $endTime,
        string $templateName = '',
        int $cursor = 0,
        int $size = 20
    ): array {
        $token    = $this->app->auth()->token();
        $data     = [
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'cursor'     => $cursor,
            'size'       => $size,
        ];
        if (!empty($templateName)) {
            $data['template_name'] = $templateName;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/report/list?access_token={$token}",
            $data
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取日志列表失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取日志详情
     *
     * @return array<string, mixed>
     */
    public function get(string $reportId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/report/get?access_token={$token}",
            ['report_id' => $reportId]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取日志详情失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取日志模板列表
     *
     * @return array<string, mixed>
     */
    public function templateList(int $offset = 0, int $size = 20): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/report/template/list?access_token={$token}",
            ['offset' => $offset, 'size' => $size]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取日志模板失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }
}
