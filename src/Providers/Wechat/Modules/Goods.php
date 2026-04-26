<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信商品库模块（微信小店/商品推广）
 */
readonly class Goods
{
    private const string BASE_URL = 'https://api.weixin.qq.com/channels/ec';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 添加商品
     *
     * @param array<string, mixed> $productData
     * @return array<string, mixed>
     */
    public function add(array $productData): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/product/add?access_token={$token}",
            $productData
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("添加商品失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取商品详情
     *
     * @return array<string, mixed>
     */
    public function get(string $productId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/product/get?access_token={$token}",
            ['product_id' => $productId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取商品失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 更新商品
     *
     * @param array<string, mixed> $productData
     * @return array<string, mixed>
     */
    public function update(array $productData): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/product/update?access_token={$token}",
            $productData
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("更新商品失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 删除商品
     *
     * @return array<string, mixed>
     */
    public function delete(string $productId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/product/delete?access_token={$token}",
            ['product_id' => $productId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("删除商品失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取商品列表
     *
     * @return array<string, mixed>
     */
    public function list(int $page = 1, int $pageSize = 20, int $status = 0): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'page'      => $page,
            'page_size' => $pageSize,
        ];
        if ($status > 0) {
            $data['status'] = $status;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/product/list/get?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取商品列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 上架商品
     *
     * @return array<string, mixed>
     */
    public function listing(string $productId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/product/listing?access_token={$token}",
            ['product_id' => $productId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("上架商品失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 下架商品
     *
     * @return array<string, mixed>
     */
    public function delisting(string $productId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/product/delisting?access_token={$token}",
            ['product_id' => $productId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("下架商品失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取订单列表
     *
     * @return array<string, mixed>
     */
    public function orderList(int $page = 1, int $pageSize = 20, string $status = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'page'      => $page,
            'page_size' => $pageSize,
        ];
        if (!empty($status)) {
            $data['status'] = $status;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/order/list/get?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取订单列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取订单详情
     *
     * @return array<string, mixed>
     */
    public function orderGet(string $orderId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/order/get?access_token={$token}",
            ['order_id' => $orderId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取订单详情失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
