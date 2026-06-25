<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信 OA 模块（打卡、汇报）
 */
readonly class Oa
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 获取打卡规则
     *
     * @param array<int, string> $userList
     * @return array<string, mixed>
     */
    public function getCheckinOption(int $datetime, array $userList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/checkin/getcheckinoption?access_token={$token}",
            ['datetime' => $datetime, 'useridlist' => $userList]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取打卡数据
     *
     * @param array<int, string> $userList
     * @return array<string, mixed>
     */
    public function getCheckinData(int $startTime, int $endTime, array $userList, int $opencheckindatatype = 3): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/checkin/getcheckindata?access_token={$token}",
            [
                'opencheckindatatype' => $opencheckindatatype,
                'starttime'           => $startTime,
                'endtime'             => $endTime,
                'useridlist'          => $userList,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取打卡日报数据
     *
     * @param array<int, string> $userList
     * @return array<string, mixed>
     */
    public function getCheckinDayData(int $startTime, int $endTime, array $userList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/checkin/getcheckin_daydata?access_token={$token}",
            [
                'starttime'  => $startTime,
                'endtime'    => $endTime,
                'useridlist' => $userList,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取打卡月报数据
     *
     * @param array<int, string> $userList
     * @return array<string, mixed>
     */
    public function getCheckinMonthData(int $startTime, int $endTime, array $userList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/checkin/getcheckin_monthdata?access_token={$token}",
            [
                'starttime'  => $startTime,
                'endtime'    => $endTime,
                'useridlist' => $userList,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取汇报记录
     *
     * @param array<int, array<string, mixed>> $filters
     * @return array<string, mixed>
     */
    public function getJournalRecordList(
        int $startTime,
        int $endTime,
        array $filters = [],
        int $limit = 100,
        string $cursor = ''
    ): array {
        $token = $this->app->auth()->token();
        $data  = [
            'starttime' => $startTime,
            'endtime'   => $endTime,
            'limit'     => $limit,
        ];
        if (!empty($filters)) {
            $data['filters'] = $filters;
        }
        if (!empty($cursor)) {
            $data['cursor'] = $cursor;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/journal/get_record_list?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取汇报统计
     *
     * @return array<string, mixed>
     */
    public function getJournalStat(int $startTime, int $endTime, string $templateId = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'starttime' => $startTime,
            'endtime'   => $endTime,
        ];
        if (!empty($templateId)) {
            $data['template_id'] = $templateId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/journal/get_stat_list?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }
}
