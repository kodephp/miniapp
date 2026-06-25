<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉考勤模块
 */
readonly class Attendance
{
    private const BASE_URL = 'https://oapi.dingtalk.com/topapi';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 获取用户考勤数据
     *
     * @param array<int, string> $userIdList
     * @return array<string, mixed>
     */
    public function list(
        string $workDateFrom,
        string $workDateTo,
        array $userIdList,
        int $offset = 0,
        int $limit = 50
    ): array {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/attendance/list?access_token={$token}",
            [
                'workDateFrom' => $workDateFrom,
                'workDateTo'   => $workDateTo,
                'userIdList'   => $userIdList,
                'offset'       => $offset,
                'limit'        => $limit,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取考勤数据失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取用户考勤班次
     *
     * @param array<int, string> $userIdList
     * @return array<string, mixed>
     */
    public function listSchedule(array $userIdList, string $workDate): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/attendance/listschedule?access_token={$token}",
            [
                'userIds'   => $userIdList,
                'workDate'  => $workDate,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取考勤班次失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取考勤组详情
     *
     * @return array<string, mixed>
     */
    public function getGroup(int $groupId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/attendance/getgroup?access_token={$token}",
            ['group_id' => $groupId]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取考勤组失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取考勤打卡记录
     *
     * @param array<int, string> $userIds
     * @return array<string, mixed>
     */
    public function getRecord(string $checkDateFrom, string $checkDateTo, array $userIds): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/attendance/getupdatedata?access_token={$token}",
            [
                'checkDateFrom' => $checkDateFrom,
                'checkDateTo'   => $checkDateTo,
                'userIds'       => $userIds,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取打卡记录失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }
}
