<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信小程序直播模块
 */
readonly class Live
{
    private const BASE_URL = 'https://api.weixin.qq.com/wxa/business';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 创建直播间
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createRoom(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/liveinfo/create?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取直播间列表
     *
     * @return array<string, mixed>
     */
    public function getLiveInfo(int $start = 0, int $limit = 10): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/liveinfo/get?access_token={$token}",
            ['start' => $start, 'limit' => $limit]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取直播间回放
     *
     * @return array<string, mixed>
     */
    public function getReplay(int $roomId, int $start = 0, int $limit = 10): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/liveinfo/get_replay?access_token={$token}",
            ['room_id' => $roomId, 'start' => $start, 'limit' => $limit]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 导入直播间商品
     *
     * @param array<int, array<string, mixed>> $goods
     * @return array<string, mixed>
     */
    public function addGoodsToRoom(int $roomId, array $goods): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/liveinfo/addgoods?access_token={$token}",
            ['room_id' => $roomId, 'goods' => $goods]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 商品添加并提审
     *
     * @param array<string, mixed> $goodsInfo
     * @return array<string, mixed>
     */
    public function addGoods(array $goodsInfo): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/goods/add?access_token={$token}",
            $goodsInfo
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 撤回商品审核
     *
     * @return array<string, mixed>
     */
    public function resetAudit(int $goodsId, int $auditId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/goods/resetaudit?access_token={$token}",
            ['goodsId' => $goodsId, 'auditId' => $auditId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 重新提交商品审核
     *
     * @return array<string, mixed>
     */
    public function audit(int $goodsId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/goods/audit?access_token={$token}",
            ['goodsId' => $goodsId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除商品
     *
     * @return array<string, mixed>
     */
    public function deleteGoods(int $goodsId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/goods/delete?access_token={$token}",
            ['goodsId' => $goodsId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 更新商品
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateGoods(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/goods/update?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取商品状态
     *
     * @param array<int, int> $goodsIds
     * @return array<string, mixed>
     */
    public function getGoodsWarehouse(array $goodsIds): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/goods/get?access_token={$token}",
            ['goods_ids' => $goodsIds]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
