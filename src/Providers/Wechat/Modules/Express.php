<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信即时配送模块
 */
readonly class Express
{
    private const string BASE_URL = 'https://api.weixin.qq.com/cgi-bin/express';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 获取已支持的配送公司列表
     *
     * @return array<string, mixed>
     */
    public function deliveryList(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/local/business/delivery/getall?access_token={$token}"
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取配送公司列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 预下配送单
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function preAddOrder(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/local/business/order/pre_add?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("预下配送单失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 下配送单
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addOrder(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/local/business/order/add?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("下配送单失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 重新下配送单
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function reOrder(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/local/business/order/readd?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("重新下配送单失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 取消配送单
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function cancelOrder(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/local/business/order/cancel?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("取消配送单失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取配送单数据
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function getOrder(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/local/business/order/get?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取配送单失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
