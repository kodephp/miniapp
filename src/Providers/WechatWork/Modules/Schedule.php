<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信日程模块
 */
readonly class Schedule
{
    private const string BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin/oa/schedule';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 创建日程
     *
     * @param array<string, mixed> $schedule
     * @return array<string, mixed>
     */
    public function add(array $schedule): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/add?access_token={$token}",
            ['schedule' => $schedule]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("创建日程失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 更新日程
     *
     * @param array<string, mixed> $schedule
     * @return array<string, mixed>
     */
    public function update(array $schedule): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/update?access_token={$token}",
            ['schedule' => $schedule]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("更新日程失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取日程详情
     *
     * @return array<string, mixed>
     */
    public function get(string $scheduleId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get?access_token={$token}",
            ['schedule_id' => $scheduleId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取日程失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 删除日程
     *
     * @return array<string, mixed>
     */
    public function delete(string $scheduleId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/del?access_token={$token}",
            ['schedule_id' => $scheduleId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("删除日程失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取日历下的日程列表
     *
     * @return array<string, mixed>
     */
    public function list(string $calId, int $offset = 0, int $limit = 500): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_by_calendar?access_token={$token}",
            [
                'cal_id' => $calId,
                'offset' => $offset,
                'limit'  => $limit,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取日程列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
