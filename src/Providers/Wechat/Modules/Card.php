<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信卡券模块
 */
readonly class Card
{
    private const BASE_URL = 'https://api.weixin.qq.com/card';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 创建卡券
     *
     * @param array<string, mixed> $cardData
     * @return array<string, mixed>
     */
    public function create(array $cardData): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/create?access_token={$token}",
            ['card' => $cardData]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("创建卡券失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取卡券详情
     *
     * @return array<string, mixed>
     */
    public function get(string $cardId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get?access_token={$token}",
            ['card_id' => $cardId]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取卡券失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 删除卡券
     *
     * @return array<string, mixed>
     */
    public function delete(string $cardId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/delete?access_token={$token}",
            ['card_id' => $cardId]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("删除卡券失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 修改卡券信息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(string $cardId, array $data): array
    {
        $token    = $this->app->auth()->token();
        $data['card_id'] = $cardId;
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/update?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("修改卡券失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 创建二维码（投放卡券）
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createQrcode(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/qrcode/create?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("创建卡券二维码失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 核销卡券
     *
     * @return array<string, mixed>
     */
    public function consume(string $code, string $cardId = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = ['code' => $code];
        if (!empty($cardId)) {
            $data['card_id'] = $cardId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/code/consume?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("核销卡券失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 查询Code
     *
     * @return array<string, mixed>
     */
    public function getCode(string $code, string $cardId = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = ['code' => $code];
        if (!empty($cardId)) {
            $data['card_id'] = $cardId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/code/get?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("查询Code失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 批量查询卡券列表
     *
     * @param array<int, string> $statusList
     * @return array<string, mixed>
     */
    public function list(int $offset = 0, int $count = 50, array $statusList = []): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'offset' => $offset,
            'count'  => $count,
        ];
        if (!empty($statusList)) {
            $data['status_list'] = $statusList;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/batchget?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("查询卡券列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 设置卡券失效
     *
     * @return array<string, mixed>
     */
    public function unavailable(string $code, string $cardId = '', string $reason = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = ['code' => $code];
        if (!empty($cardId)) {
            $data['card_id'] = $cardId;
        }
        if (!empty($reason)) {
            $data['reason'] = $reason;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/code/unavailable?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("设置卡券失效失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
