<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信公费电话模块
 */
readonly class Dial
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 拨打公费电话
     *
     * @return array<string, mixed>
     */
    public function call(string $caller, array $calleeList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/dial/get_dial_detail?access_token={$token}",
            [
                'caller'     => $caller,
                'callee'     => $calleeList,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取公费电话拨打记录
     *
     * @return array<string, mixed>
     */
    public function records(int $startTime, int $endTime, int $offset = 0, int $limit = 100): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/dial/get_dial_record?access_token={$token}",
            [
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'offset'     => $offset,
                'limit'      => $limit,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
