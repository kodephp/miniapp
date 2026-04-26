<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信附近小程序模块
 */
readonly class Nearby
{
    private const BASE_URL = 'https://api.weixin.qq.com/wxa';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 添加地点
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function addPoi(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/addnearbypoi?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除地点
     *
     * @return array<string, mixed>
     */
    public function deletePoi(string $poiId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/delnearbypoi?access_token={$token}",
            ['poi_id' => $poiId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查看地点列表
     *
     * @return array<string, mixed>
     */
    public function listPoi(int $page = 1, int $pageRows = 10): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/getnearbypoilist?access_token={$token}&page={$page}&page_rows={$pageRows}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 展示/取消展示附近小程序
     *
     * @return array<string, mixed>
     */
    public function setStatus(string $poiId, int $status): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/setnearbypoishowstatus?access_token={$token}",
            ['poi_id' => $poiId, 'status' => $status]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
