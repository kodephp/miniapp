<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Core\Crypto\Aes128CbcPkcs7;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信小程序客户端敏感数据解密
 *
 * 用于解密 wx.getUserProfile、getPhoneNumber 等接口经客户端回传的 encryptedData。
 * 底层算法见 {@see Aes128CbcPkcs7}（微信 / 抖音 / QQ 通用）。
 *
 * 安全约束：
 *   - 解密后的 watermark.appid 必须与当前小程序 appId 一致，否则视为伪造数据。
 *   - session_key 属敏感凭证，严禁写入日志（LogSanitizer 已覆盖脱敏）。
 */
final class Decrypt
{
    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 解密 encryptedData，返回原始数组
     *
     * @param bool $verifyAppId 是否校验 watermark.appid（默认开启，生产环境务必保持开启）
     *
     * @throws ApiException 解密失败、结果非 JSON 或 watermark 校验不通过
     * @return array<string, mixed>
     */
    public function data(
        string $encryptedData,
        string $sessionKey,
        string $iv,
        bool $verifyAppId = true,
    ): array {
        return Aes128CbcPkcs7::decrypt(
            $this->app->config()->appId(),
            $encryptedData,
            $sessionKey,
            $iv,
            $verifyAppId,
        );
    }

    /**
     * 解密手机号（getPhoneNumber）
     *
     * 返回数组含 phoneNumber / purePhoneNumber / countryCode / watermark 字段。
     *
     * @return array<string, mixed>
     *
     * @throws ApiException 解密失败或缺少手机号字段
     */
    public function phone(string $encryptedData, string $sessionKey, string $iv): array
    {
        $data = $this->data($encryptedData, $sessionKey, $iv);

        foreach (['phoneNumber', 'purePhoneNumber', 'countryCode'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                throw new ApiException("微信手机号解密结果缺少字段：{$key}", -1);
            }
        }

        return $data;
    }

    /**
     * 解密用户资料（getUserProfile）
     *
     * @return array<string, mixed>
     */
    public function userInfo(string $encryptedData, string $sessionKey, string $iv): array
    {
        return $this->data($encryptedData, $sessionKey, $iv);
    }
}
