<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core\Crypto;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Utils\Sign;

/**
 * RSA + PKCS#1 v1.5 非对称解密（抖音开放平台敏感数据通用）
 *
 * 抖音开放平台的部分敏感数据（getPhoneNumber 组件 code 换取的手机号、
 * 商家券「用户手机号授权结果回调」中的 encrypted_phone 等）并非用 session_key
 * 对称加密，而是用开发者在「抖音开放平台-控制台-开发配置-应用公钥」处录入的
 * **应用公钥**做 RSA 加密，需由开发者用对应的**应用私钥**解密：
 *
 *   - 填充方式 = PKCS#1 v1.5（服务端使用 Go 的 rsa.EncryptPKCS1v15）
 *   - 密文传输 = base64 编码
 *   - 明文长度超过单块上限时服务端会分块加密，故解密同样按块处理
 *
 * 私钥支持完整 PEM、纯 Base64、PKCS#1 与 PKCS#8 四种写法
 * （由 {@see Sign::normalizePrivateKey()} 归一化）。
 *
 * 安全约束：应用私钥属最高级敏感凭证，严禁写入日志或下发至客户端。
 */
final class RsaPkcs1
{
    /**
     * 解密 base64 密文，返回明文字符串
     *
     * @param string $cipher     base64 编码的密文
     * @param string $privateKey 应用私钥（PEM / 纯 Base64 / PKCS#1 / PKCS#8）
     *
     * @throws ApiException 私钥无效、密文非法 base64 或解密失败
     */
    public static function decrypt(string $cipher, string $privateKey): string
    {
        if (trim($privateKey) === '') {
            throw new ApiException('RSA 解密失败：未配置应用私钥', -1);
        }

        $key = openssl_pkey_get_private(Sign::normalizePrivateKey($privateKey));
        if ($key === false) {
            throw new ApiException('RSA 解密失败：应用私钥无效（支持 PKCS#1 / PKCS#8）', -1);
        }

        $raw = base64_decode($cipher, true);
        if ($raw === false || $raw === '') {
            throw new ApiException('RSA 解密失败：密文不是合法的 base64', -1);
        }

        $blockSize = self::blockSize($key);
        if (strlen($raw) % $blockSize !== 0) {
            throw new ApiException('RSA 解密失败：密文长度与密钥长度不匹配', -1);
        }

        $plain = '';
        foreach (str_split($raw, $blockSize) as $block) {
            $decrypted = '';
            if (!openssl_private_decrypt($block, $decrypted, $key, OPENSSL_PKCS1_PADDING)) {
                throw new ApiException('RSA 解密失败：私钥与加密所用公钥不匹配', -1);
            }
            $plain .= $decrypted;
        }

        return $plain;
    }

    /**
     * 解密并解析为 JSON 数组
     *
     * @throws ApiException 解密失败或结果不是合法 JSON 对象
     * @return array<string, mixed>
     */
    public static function decryptJson(string $cipher, string $privateKey): array
    {
        $payload = json_decode(self::decrypt($cipher, $privateKey), true);
        if (!is_array($payload)) {
            throw new ApiException('RSA 解密失败：结果不是合法 JSON', -1);
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * 密钥模长（字节），即单个密文块的长度
     *
     * @param \OpenSSLAsymmetricKey $key
     *
     * @return int<1, max>
     */
    private static function blockSize(\OpenSSLAsymmetricKey $key): int
    {
        $details = openssl_pkey_get_details($key);
        $bits    = is_array($details) ? ($details['bits'] ?? 0) : 0;
        if (!is_int($bits) || $bits < 512) {
            throw new ApiException('RSA 解密失败：无法解析应用私钥长度', -1);
        }

        $size = intdiv($bits, 8);
        \assert($size >= 1);

        return $size;
    }
}
