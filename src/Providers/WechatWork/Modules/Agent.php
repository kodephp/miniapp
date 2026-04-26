<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信应用管理模块
 */
readonly class Agent
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 获取应用详情
     *
     * @return array<string, mixed>
     */
    public function get(int $agentId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/agent/get?access_token={$token}&agentid={$agentId}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 设置应用
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function set(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/agent/set?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取应用列表
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/agent/list?access_token={$token}"
        );

        return json_decode((string) $response->getBody(), true);
    }
}
