<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core\Crypto;

use Kode\MiniApp\Exceptions\ApiException;

/**
 * AES-128-CBC + PKCS#7 客户端敏感数据解密（微信 / 抖音 / QQ 通用）
 *
 * 微信、抖音、QQ 小程序客户端回传的 encryptedData 采用同一套对称算法：
 *   - 密钥 key   = base64_decode(session_key)，长度固定 16 字节（AES-128）
 *   - 向量 iv    = base64_decode(iv)，长度固定 16 字节
 *   - 密文       = base64_decode(encryptedData)
 *   - 对称算法   = AES-128-CBC，PKCS#7 填充（openssl 解密时自动去除）
 *   - 明文为 JSON，结构内含 watermark:{appid, timestamp}
 *
 * 安全约束：
 *   - 解密后的 watermark.appid 必须与当前小程序 appId 一致，否则视为伪造数据。
 *   - session_key 属敏感凭证，严禁写入日志（LogSanitizer 已覆盖脱敏）。
 */
final class Aes128CbcPkcs7
{
    /**
     * 解密并返回原始数组
     *
     * @param bool   $verifyAppId 是否校验 watermark.appid（默认开启，生产环境务必保持开启）
     *
     * @throws ApiException 解密失败、结果非 JSON 或 watermark 校验不通过
     * @return array<string, mixed>
     */
    public static function decrypt(
        string $appId,
        string $encryptedData,
        string $sessionKey,
        string $iv,
        bool $verifyAppId = true,
    ): array {
        $key  = base64_decode($sessionKey, true);
        $data = base64_decode($encryptedData, true);
        $vec  = base64_decode($iv, true);

        if ($key === false || $data === false || $vec === false) {
            throw new ApiException('敏感数据解密失败：base64 解析错误', -1);
        }
        if (strlen($key) !== 16 || strlen($vec) !== 16) {
            throw new ApiException('敏感数据解密失败：密钥或向量长度非法', -1);
        }

        $decrypted = openssl_decrypt($data, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $vec);
        if ($decrypted === false || $decrypted === '') {
            throw new ApiException('敏感数据解密失败：AES 解密错误', -1);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($decrypted, true);
        if (!is_array($payload)) {
            throw new ApiException('敏感数据解密失败：结果不是合法 JSON', -1);
        }

        if ($verifyAppId) {
            self::assertWatermark($appId, $payload);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assertWatermark(string $appId, array $payload): void
    {
        $watermark = $payload['watermark'] ?? null;
        if (!is_array($watermark)) {
            throw new ApiException('敏感数据解密失败：缺少 watermark 节点', -1);
        }

        $watermarkAppId = $watermark['appid'] ?? null;
        if (!is_string($watermarkAppId) || $watermarkAppId !== $appId) {
            throw new ApiException('敏感数据解密失败：watermark.appid 校验不通过', -1);
        }
    }
}
