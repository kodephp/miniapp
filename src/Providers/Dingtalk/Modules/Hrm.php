<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉智能人事模块
 */
readonly class Hrm
{
    private const BASE_URL = 'https://oapi.dingtalk.com/topapi';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 获取员工花名册字段信息
     *
     * @param array<string> $fieldFilterList
     * @return array<string, mixed>
     */
    public function getEmpRosterDetail(array $userIds, array $fieldFilterList = []): array
    {
        $token    = $this->app->auth()->token();
        $data     = [
            'userid_list' => $userIds,
        ];
        if (!empty($fieldFilterList)) {
            $data['field_filter_list'] = $fieldFilterList;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/smartwork/hrm/employee/v2/list?access_token={$token}",
            $data
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取员工花名册失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取在职员工列表
     *
     * @return array<string, mixed>
     */
    public function queryOnJob(array $statusList = [2, 3, 5], int $offset = 0, int $size = 50): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/smartwork/hrm/employee/queryonjob?access_token={$token}",
            [
                'status_list' => $statusList,
                'offset'      => $offset,
                'size'        => $size,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取在职员工列表失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取待入职员工列表
     *
     * @return array<string, mixed>
     */
    public function queryPreEntry(int $offset = 0, int $size = 50): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/smartwork/hrm/employee/querypreentry?access_token={$token}",
            [
                'offset' => $offset,
                'size'   => $size,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取待入职员工列表失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取离职员工列表
     *
     * @return array<string, mixed>
     */
    public function queryDimission(int $offset = 0, int $size = 50): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/smartwork/hrm/employee/querydimission?access_token={$token}",
            [
                'offset' => $offset,
                'size'   => $size,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取离职员工列表失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }
}
