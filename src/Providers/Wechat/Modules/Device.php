<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信设备功能模块
 */
readonly class Device
{
    private const BASE_URL = 'https://api.weixin.qq.com/device';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 获取设备二维码
     *
     * @param array<int, array<string, mixed>> $deviceList
     * @return array<string, mixed>
     */
    public function getQrcode(array $deviceList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/getqrcode?access_token={$token}",
            ['device_num' => count($deviceList), 'device_list' => $deviceList]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取设备二维码失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 授权设备
     *
     * @param array<int, array<string, mixed>> $deviceList
     * @return array<string, mixed>
     */
    public function authorize(array $deviceList, string $productId = '', string $opType = '0'): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'device_num'  => count($deviceList),
            'device_list' => $deviceList,
            'op_type'     => $opType,
        ];
        if (!empty($productId)) {
            $data['product_id'] = $productId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/authorize_device?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("授权设备失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 查询设备状态
     *
     * @return array<string, mixed>
     */
    public function getStat(string $deviceId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/get_stat?access_token={$token}&device_id={$deviceId}"
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("查询设备状态失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 绑定用户和设备
     *
     * @return array<string, mixed>
     */
    public function bind(string $ticket, string $deviceId, string $openid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/bind?access_token={$token}",
            [
                'ticket'    => $ticket,
                'device_id' => $deviceId,
                'openid'    => $openid,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("绑定设备失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 解绑用户和设备
     *
     * @return array<string, mixed>
     */
    public function unbind(string $ticket, string $deviceId, string $openid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/unbind?access_token={$token}",
            [
                'ticket'    => $ticket,
                'device_id' => $deviceId,
                'openid'    => $openid,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("解绑设备失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 强制绑定用户和设备
     *
     * @return array<string, mixed>
     */
    public function compelBind(string $deviceId, string $openid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/compel_bind?access_token={$token}",
            [
                'device_id' => $deviceId,
                'openid'    => $openid,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("强制绑定设备失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 强制解绑用户和设备
     *
     * @return array<string, mixed>
     */
    public function compelUnbind(string $deviceId, string $openid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/compel_unbind?access_token={$token}",
            [
                'device_id' => $deviceId,
                'openid'    => $openid,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("强制解绑设备失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
