<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信门店小程序模块
 */
readonly class Store
{
    private const BASE_URL = 'https://api.weixin.qq.com/wxa';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 创建门店小程序
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/store?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取门店列表
     *
     * @return array<string, mixed>
     */
    public function list(int $offset = 0, int $limit = 20): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_store_list?access_token={$token}",
            ['offset' => $offset, 'limit' => $limit]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取单个门店信息
     *
     * @return array<string, mixed>
     */
    public function get(int $poiId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_store?access_token={$token}",
            ['poi_id' => $poiId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 修改门店信息
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/update_store?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除门店
     *
     * @return array<string, mixed>
     */
    public function delete(int $poiId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/del_store?access_token={$token}",
            ['poi_id' => $poiId]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
