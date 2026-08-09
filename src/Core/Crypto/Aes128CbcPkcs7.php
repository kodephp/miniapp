<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core\Crypto;

use Kode\MiniApp\Exceptions\ApiException;

/**
 * AES-128-CBC + PKCS#7 客户端敏感数据解密（微信 / 抖音 / QQ / 百度 / 飞书通用）
 *
 * 微信、抖音、QQ、百度小程序客户端回传的 encryptedData 采用同一套对称算法，
 * 飞书虽为同一族 AES-128-CBC，但 session_key 与 iv 采用 hex 编码（而非 base64）、
 * 且明文不含 watermark。为此本工具通过 $encoding 参数兼容两种变体：
 *
 *   - base64（默认）：key = base64_decode(session_key)，vector = base64_decode(iv)
 *   - hex：           key = hex2bin(session_key)，   vector = hex2bin(iv)
 *   - 密文 encryptedData 两种变体都用 base64 编码
 *
 *   - 对称算法   = AES-128-CBC，PKCS#7 填充（openssl 解密时自动去除）
 *   - 明文为 JSON；微信 / 抖音 / QQ / 百度 结构内含 watermark:{appid, timestamp}
 *
 * 安全约束：
 *   - 解密后的 watermark.appid 必须与当前小程序 appId 一致，否则视为伪造数据
 *     （飞书无 watermark，调用方应传 verifyAppId=false）。
 *   - session_key 属敏感凭证，严禁写入日志（LogSanitizer 已覆盖脱敏）。
 */
final class Aes128CbcPkcs7
{
    /**
     * 解密并返回原始数组
     *
     * @param bool   $verifyAppId 是否校验 watermark.appid（默认开启，生产环境务必保持开启）
     * @param string $encoding    session_key / iv 的编码方式：'base64'（微信系）或 'hex'（飞书）
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
        string $encoding = 'base64',
    ): array {
        $encErr = '';
        $key    = self::decodeCredential($sessionKey, $encoding, $encErr);
        $vec    = self::decodeCredential($iv, $encoding, $encErr);
        $data   = base64_decode($encryptedData, true);

        if ($key === null || $data === false || $vec === null) {
            throw new ApiException("敏感数据解密失败：{$encErr} 解析错误", -1);
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
     * 按编码方式解码密钥 / 向量
     *
     * @return string|null 解码失败返回 null
     */
    private static function decodeCredential(string $value, string $encoding, string &$encErr): ?string
    {
        if ($encoding === 'hex') {
            $encErr = 'hex';
            $clean  = (string) preg_replace('/[^0-9a-fA-F]/', '', $value);
            if ($clean === '' || strlen($clean) % 2 !== 0) {
                return null;
            }
            $decoded = hex2bin($clean);

            return is_string($decoded) ? $decoded : null;
        }

        $encErr = 'base64';
        $decoded = base64_decode($value, true);

        return is_string($decoded) ? $decoded : null;
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
