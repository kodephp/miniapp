<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信会话内容存档模块
 */
readonly class Msghub
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin/msghub';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 获取会话内容存档开启成员列表
     *
     * @return array<string, mixed>
     */
    public function getPermitUserList(int $type = 1): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_permit_user_list?access_token={$token}",
            ['type' => $type]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取开启成员列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取会话同意情况
     *
     * @param array<int, string> $userList
     * @return array<string, mixed>
     */
    public function getSingleAgreeStatus(array $userList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_single_agree_status?access_token={$token}",
            ['userid_list' => $userList]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取同意情况失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取群聊会话同意情况
     *
     * @param array<int, string> $roomList
     * @return array<string, mixed>
     */
    public function getRoomAgreeStatus(array $roomList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_room_agree_status?access_token={$token}",
            ['roomid_list' => $roomList]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取群聊同意情况失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取会话内容存档内部群信息
     *
     * @return array<string, mixed>
     */
    public function getRoomInfo(string $roomid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_room_info?access_token={$token}",
            ['roomid' => $roomid]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取群信息失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
