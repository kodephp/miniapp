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
     * @param array<string, mixed> $params
     */
    public static function rsa(array $params, string $privateKey, string $algo = 'sha256'): string
    {
        ksort($params);
        $string = urldecode(http_build_query($params));

        $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
               wordwrap($privateKey, 64, "\n", true) .
               "\n-----END RSA PRIVATE KEY-----";

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
     * @param array<string, mixed> $params
     */
    public static function verifyRsa(array $params, string $publicKey, string $sign, string $algo = 'sha256'): bool
    {
        ksort($params);
        $string = urldecode(http_build_query($params));

        $key = "-----BEGIN PUBLIC KEY-----\n" .
               wordwrap($publicKey, 64, "\n", true) .
               "\n-----END PUBLIC KEY-----";

        $algoConstant = match ($algo) {
            'sha256' => OPENSSL_ALGO_SHA256,
            'sha1'   => OPENSSL_ALGO_SHA1,
            default  => OPENSSL_ALGO_SHA256,
        };

        return openssl_verify($string, base64_decode($sign), $key, $algoConstant) === 1;
    }
}
