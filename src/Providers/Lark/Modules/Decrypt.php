<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Core\Crypto\Aes128CbcPkcs7;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书小程序客户端敏感数据解密
 *
 * 用于解密 tt.getPhoneNumber 等接口经客户端回传的 encryptedData。
 * 飞书与微信同族 AES-128-CBC + PKCS#7 算法，但存在两处差异：
 *   - session_key 与 iv 采用 **hex** 编码（微信系为 base64），见 {@see Aes128CbcPkcs7} 的 $encoding 参数
 *   - 解密结果内 **不含 watermark**，故默认不校验 appid（调用方应传 verifyAppId=false）
 *
 * 安全约束：
 *   - session_key 属敏感凭证，严禁写入日志（LogSanitizer 已覆盖脱敏）。
 */
final class Decrypt
{
    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 解密 encryptedData，返回原始数组
     *
     * 飞书明文不含 watermark，故 $verifyAppId 默认关闭。
     *
     * @param bool $verifyAppId 是否校验 watermark.appid（飞书无 watermark，应始终为 false）
     *
     * @throws ApiException 解密失败、结果非 JSON、编码或长度非法
     * @return array<string, mixed>
     */
    public function data(
        string $encryptedData,
        string $sessionKey,
        string $iv,
        bool $verifyAppId = false,
    ): array {
        return Aes128CbcPkcs7::decrypt(
            $this->app->config()->appId(),
            $encryptedData,
            $sessionKey,
            $iv,
            $verifyAppId,
            'hex',
        );
    }

    /**
     * 解密手机号（tt.getPhoneNumber）
     *
     * 返回数组含 phoneNumber 字段。
     *
     * @return array<string, mixed>
     *
     * @throws ApiException 解密失败或缺少手机号字段
     */
    public function phone(string $encryptedData, string $sessionKey, string $iv): array
    {
        $data = $this->data($encryptedData, $sessionKey, $iv);

        if (!isset($data['phoneNumber']) || !is_string($data['phoneNumber'])) {
            throw new ApiException('飞书手机号解密结果缺少字段：phoneNumber', -1);
        }

        return $data;
    }

    /**
     * 解密用户资料（getUserInfo）
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
     * @param bool $verifyAppId 是否校验 watermark.appid（飞书无 watermark，应始终为 false）
     *
     * @throws ApiException 未托管 session_key、解密失败、结果非 JSON
     * @return array<string, mixed>
     */
    public function dataByUser(string $encryptedData, string $iv, string $openId, bool $verifyAppId = false): array
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
     * 一站式解密手机号（tt.getPhoneNumber，自动取用托管 session_key）
     *
     * @return array<string, mixed>
     *
     * @throws ApiException 未托管 session_key、解密失败或缺少手机号字段
     */
    public function phoneByUser(string $encryptedData, string $iv, string $openId): array
    {
        $data = $this->dataByUser($encryptedData, $iv, $openId);

        if (!isset($data['phoneNumber']) || !is_string($data['phoneNumber'])) {
            throw new ApiException('飞书手机号解密结果缺少字段：phoneNumber', -1);
        }

        return $data;
    }

    /**
     * 一站式解密用户资料（getUserInfo，自动取用托管 session_key）
     *
     * @return array<string, mixed>
     */
    public function userInfoByUser(string $encryptedData, string $iv, string $openId): array
    {
        return $this->dataByUser($encryptedData, $iv, $openId);
    }
}
