<?php

declare(strict_types=1);

namespace Kode\MiniApp\Utils;

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
        $string = urldecode(http_build_query($params));

        $key = self::normalizePrivateKey($privateKey);

        $algoConstant = match ($algo) {
            'sha256' => OPENSSL_ALGO_SHA256,
            'sha1'   => OPENSSL_ALGO_SHA1,
            default  => OPENSSL_ALGO_SHA256,
        };

        openssl_sign($string, $sign, $key, $algoConstant);

        return base64_encode($sign);
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
        $string = urldecode(http_build_query($params));

        $key = self::normalizePublicKey($publicKey);

        $algoConstant = match ($algo) {
            'sha256' => OPENSSL_ALGO_SHA256,
            'sha1'   => OPENSSL_ALGO_SHA1,
            default  => OPENSSL_ALGO_SHA256,
        };

        return openssl_verify($string, base64_decode($sign), $key, $algoConstant) === 1;
    }

    /**
     * 标准化私钥格式（支持完整 PEM 或纯 Base64）
     */
    private static function normalizePrivateKey(string $key): string
    {
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN RSA PRIVATE KEY-----\n" .
               wordwrap($key, 64, "\n", true) .
               "\n-----END RSA PRIVATE KEY-----";
    }

    /**
     * 标准化公钥格式（支持完整 PEM 或纯 Base64）
     */
    private static function normalizePublicKey(string $key): string
    {
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN PUBLIC KEY-----\n" .
               wordwrap($key, 64, "\n", true) .
               "\n-----END PUBLIC KEY-----";
    }
}
