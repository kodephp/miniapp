<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信会议室模块
 */
readonly class Meeting
{
    private const string BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 创建会议室
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/meetingroom/add?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 编辑会议室
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/meetingroom/edit?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除会议室
     *
     * @return array<string, mixed>
     */
    public function delete(int $meetingRoomId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/meetingroom/del?access_token={$token}",
            ['meetingroom_id' => $meetingRoomId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取会议室列表
     *
     * @return array<string, mixed>
     */
    public function list(int $cityId = 0, int $buildingId = 0, int $floorId = 0, string $equipmentId = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = [];
        if ($cityId > 0) {
            $data['city_id'] = $cityId;
        }
        if ($buildingId > 0) {
            $data['building_id'] = $buildingId;
        }
        if ($floorId > 0) {
            $data['floor_id'] = $floorId;
        }
        if (!empty($equipmentId)) {
            $data['equipment_id'] = $equipmentId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/meetingroom/list?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询会议室预订信息
     *
     * @return array<string, mixed>
     */
    public function getBookingInfo(int $meetingRoomId, string $startTime, string $endTime): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/meetingroom/get_booking_info?access_token={$token}",
            [
                'meetingroom_id' => $meetingRoomId,
                'start_time'     => $startTime,
                'end_time'       => $endTime,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 预订会议室
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function book(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/meetingroom/book?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 取消预订会议室
     *
     * @return array<string, mixed>
     */
    public function cancelBook(string $meetingId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/meetingroom/cancel_book?access_token={$token}",
            ['meeting_id' => $meetingId]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
