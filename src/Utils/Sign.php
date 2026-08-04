<?php

declare(strict_types=1);

namespace Kode\MiniApp\Utils;

use Kode\MiniApp\Exceptions\ConfigException;

/**
 * 签名工具类
 */
final class Sign
{
    /**
     * MD5 签名
     *
     * @param array<string, mixed> $params
     */
    public static function md5(array $params, string $key): string
    {
        ksort($params);
        $string = urldecode(http_build_query($params)) . '&key=' . $key;

        return strtoupper(md5($string));
    }

    /**
     * HMAC-SHA256 签名
     *
     * @param array<string, mixed> $params
     */
    public static function hmac(array $params, string $key): string
    {
        ksort($params);
        $string = urldecode(http_build_query($params)) . '&key=' . $key;

        return strtoupper(hash_hmac('sha256', $string, $key));
    }

    /**
     * RSA2 签名
     *
     * 支持传入完整 PEM 格式或纯 Base64 字符串
     *
     * @param array<string, mixed> $params
     */
    public static function rsa(array $params, string $privateKey, string $algo = 'sha256'): string
    {
        ksort($params);

        return self::rsaRaw(urldecode(http_build_query($params)), $privateKey, $algo);
    }

    /**
     * 对原始字符串做 RSA 签名
     */
    public static function rsaRaw(string $content, string $privateKey, string $algo = 'sha256'): string
    {
        $key = openssl_pkey_get_private(self::normalizePrivateKey($privateKey));
        if ($key === false) {
            throw new ConfigException('RSA 私钥无效，请检查 private_key 配置（支持 PKCS#1 / PKCS#8）');
        }

        $signature = '';
        openssl_sign($content, $signature, $key, self::algo($algo));

        return base64_encode($signature);
    }

    /**
     * 验证 RSA2 签名
     *
     * 支持传入完整 PEM 格式或纯 Base64 字符串
     *
     * @param array<string, mixed> $params
     */
    public static function verifyRsa(array $params, string $publicKey, string $sign, string $algo = 'sha256'): bool
    {
        ksort($params);

        return self::verifyRsaRaw(urldecode(http_build_query($params)), $publicKey, $sign, $algo);
    }

    /**
     * 对原始字符串验签
     */
    public static function verifyRsaRaw(
        string $content,
        string $publicKey,
        string $sign,
        string $algo = 'sha256',
    ): bool {
        $key = openssl_pkey_get_public(self::normalizePublicKey($publicKey));
        if ($key === false) {
            return false;
        }

        $decoded = base64_decode($sign, true);
        if ($decoded === false) {
            return false;
        }

        return openssl_verify($content, $decoded, $key, self::algo($algo)) === 1;
    }

    /**
     * 标准化私钥格式
     *
     * 支持完整 PEM、纯 Base64（PKCS#1）以及支付宝开放平台常见的 PKCS#8 字符串。
     */
    public static function normalizePrivateKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || str_contains($key, '-----BEGIN')) {
            return $key;
        }

        $body  = wordwrap($key, 64, "\n", true);
        $pkcs1 = "-----BEGIN RSA PRIVATE KEY-----\n{$body}\n-----END RSA PRIVATE KEY-----";

        if (openssl_pkey_get_private($pkcs1) !== false) {
            return $pkcs1;
        }

        return "-----BEGIN PRIVATE KEY-----\n{$body}\n-----END PRIVATE KEY-----";
    }

    /**
     * 标准化公钥格式（支持完整 PEM 或纯 Base64）
     */
    public static function normalizePublicKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN PUBLIC KEY-----\n" .
               wordwrap($key, 64, "\n", true) .
               "\n-----END PUBLIC KEY-----";
    }

    private static function algo(string $algo): int
    {
        return match ($algo) {
            'sha1'  => OPENSSL_ALGO_SHA1,
            default => OPENSSL_ALGO_SHA256,
        };
    }
}
