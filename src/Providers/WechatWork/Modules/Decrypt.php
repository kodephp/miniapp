<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Core\Crypto\Aes128CbcPkcs7;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信小程序客户端敏感数据解密
 *
 * 算法与微信小程序完全一致（AES-128-CBC + PKCS#7 + session_key），
 * 仅明文 watermark.appid 实为「企业 corpid」而非小程序 appId。
 * 底层工具见 {@see Aes128CbcPkcs7}（微信 / 抖音 / QQ / 百度 / 飞书通用）。
 *
 * 安全约束：
 *   - 解密后的 watermark.appid 必须与当前企业 corpid 一致，否则视为伪造数据。
 *   - session_key 属敏感凭证，严禁写入日志（LogSanitizer 已覆盖脱敏）。
 */
final class Decrypt
{
    public function __construct(
        private WechatWorkApp $app,
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
            $this->app->config()->corpId(),
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
                throw new ApiException("企业微信手机号解密结果缺少字段：{$key}", -1);
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

    /**
     * 一站式解密（登录即托管的 session_key 自动取用）
     *
     * 无需再手动传 session_key：只要登录阶段已缓存该用户的 session_key，
     * 传入 openid 即可自动取回密钥完成解密。
     *
     * @param bool $verifyAppId 是否校验 watermark.appid（默认开启，生产环境务必保持开启）
     *
     * @throws ApiException 未托管 session_key、解密失败、结果非 JSON 或 watermark 校验不通过
     * @return array<string, mixed>
     */
    public function dataByUser(string $encryptedData, string $iv, string $openId, bool $verifyAppId = true): array
    {
        $sessionKey = SessionKeyManager::for($this->app->config())->get($openId);
        if ($sessionKey === null) {
            throw new ApiException(
                "未找到用户 [{$openId}] 的 session_key 缓存，请先调用登录接口（自动托管）或手动 SessionKeyManager::store()",
                -1,
            );
        }

        return $this->data($encryptedData, $sessionKey, $iv, $verifyAppId);
    }

    /**
     * 一站式解密手机号（getPhoneNumber，自动取用托管 session_key）
     *
     * @return array<string, mixed>
     *
     * @throws ApiException 未托管 session_key、解密失败或缺少手机号字段
     */
    public function phoneByUser(string $encryptedData, string $iv, string $openId): array
    {
        $data = $this->dataByUser($encryptedData, $iv, $openId);

        foreach (['phoneNumber', 'purePhoneNumber', 'countryCode'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                throw new ApiException("企业微信手机号解密结果缺少字段：{$key}", -1);
            }
        }

        return $data;
    }

    /**
     * 一站式解密用户资料（getUserProfile，自动取用托管 session_key）
     *
     * @return array<string, mixed>
     */
    public function userInfoByUser(string $encryptedData, string $iv, string $openId): array
    {
        return $this->dataByUser($encryptedData, $iv, $openId);
    }
}
