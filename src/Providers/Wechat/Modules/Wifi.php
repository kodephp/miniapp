<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信连Wi-Fi模块
 */
readonly class Wifi
{
    private const string BASE_URL = 'https://api.weixin.qq.com/bizwifi';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 添加密码型设备
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addDevice(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/device/add?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("添加设备失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 查询设备列表
     *
     * @return array<string, mixed>
     */
    public function deviceList(int $pageIndex = 1, int $pageSize = 10, int $shopId = 0): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'pageindex' => $pageIndex,
            'pagesize'  => $pageSize,
        ];
        if ($shopId > 0) {
            $data['shop_id'] = $shopId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/device/list?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("查询设备列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 删除设备
     *
     * @return array<string, mixed>
     */
    public function deleteDevice(string $bssid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/device/delete?access_token={$token}",
            ['bssid' => $bssid]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("删除设备失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取Wi-Fi二维码
     *
     * @return array<string, mixed>
     */
    public function getQrcode(int $shopId, string $ssid = '', int $imgId = 1): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'shop_id' => $shopId,
            'img_id'  => $imgId,
        ];
        if (!empty($ssid)) {
            $data['ssid'] = $ssid;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/qrcode/get?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取二维码失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 设置商家主页
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function setHomePage(int $shopId, array $data): array
    {
        $token = $this->app->auth()->token();
        $data['shop_id'] = $shopId;
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/homepage/set?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("设置主页失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 查询商家主页
     *
     * @return array<string, mixed>
     */
    public function getHomePage(int $shopId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/homepage/get?access_token={$token}",
            ['shop_id' => $shopId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("查询主页失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 统计信息
     *
     * @return array<string, mixed>
     */
    public function statistics(int $shopId, string $beginDate, string $endDate): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/statistics/get?access_token={$token}",
            [
                'shop_id'     => $shopId,
                'begin_date'  => $beginDate,
                'end_date'    => $endDate,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取统计失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
