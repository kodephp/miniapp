<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信摇一摇模块
 */
readonly class Shake
{
    private const BASE_URL = 'https://api.weixin.qq.com/shakearound';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 申请设备ID
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function applyDeviceId(int $quantity, array $data = []): array
    {
        $token    = $this->app->auth()->token();
        $postData = array_merge([
            'quantity' => $quantity,
        ], $data);
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/device/applyid?access_token={$token}",
            $postData
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("申请设备ID失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 查询设备列表
     *
     * @return array<string, mixed>
     */
    public function deviceList(int $applyId, int $offset = 0, int $count = 50): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/device/search?access_token={$token}",
            [
                'type'     => 1,
                'apply_id' => $applyId,
                'offset'   => $offset,
                'count'    => $count,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("查询设备列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 配置设备与页面的关联关系
     *
     * @param array<int, array<string, mixed>> $devices
     * @param array<int, array<string, mixed>> $pages
     * @return array<string, mixed>
     */
    public function bindPage(array $devices, array $pages, int $bindType = 1, int $appendType = 1): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/device/bindpage?access_token={$token}",
            [
                'device_identifiers' => $devices,
                'page_ids'           => $pages,
                'bind'               => $bindType,
                'append'             => $appendType,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("绑定页面失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 新增页面
     *
     * @param array<string, mixed> $pageData
     * @return array<string, mixed>
     */
    public function addPage(array $pageData): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/page/add?access_token={$token}",
            $pageData
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("新增页面失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 查询页面列表
     *
     * @return array<string, mixed>
     */
    public function pageList(int $offset = 0, int $count = 50): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/page/search?access_token={$token}",
            [
                'type'   => 2,
                'offset' => $offset,
                'count'  => $count,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("查询页面列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 删除页面
     *
     * @param array<int> $pageIds
     * @return array<string, mixed>
     */
    public function deletePage(array $pageIds): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/page/delete?access_token={$token}",
            ['page_ids' => $pageIds]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("删除页面失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取摇周边的设备及用户信息
     *
     * @return array<string, mixed>
     */
    public function getShakeInfo(string $ticket): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/user/getshakeinfo?access_token={$token}",
            ['ticket' => $ticket]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取摇一摇信息失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 数据统计
     *
     * @return array<string, mixed>
     */
    public function statistics(int $pageId, int $beginDate, int $endDate): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/statistics/page?access_token={$token}",
            [
                'page_id'    => $pageId,
                'begin_date' => $beginDate,
                'end_date'   => $endDate,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取统计数据失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
