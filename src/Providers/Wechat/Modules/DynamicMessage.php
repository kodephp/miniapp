<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信动态消息模块
 */
readonly class DynamicMessage
{
    private const BASE_URL = 'https://api.weixin.qq.com/cgi-bin/message/wxopen';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 创建被分享动态消息的 activity_id
     *
     * @return array<string, mixed>
     */
    public function createActivityId(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/activityid/create?access_token={$token}"
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("创建活动ID失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 修改被分享的动态消息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function setUpdatableMsg(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/updatablemsg/send?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("更新动态消息失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
