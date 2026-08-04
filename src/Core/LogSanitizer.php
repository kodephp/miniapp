<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

/**
 * 日志敏感信息脱敏器
 *
 * 开放平台的 URL 与请求体里普遍夹带 secret / access_token / 手机号，
 * 直接写入 debug 日志会造成凭据泄露。本类统一做掩码处理。
 *
 * 用法：
 *   LogSanitizer::uri('https://api.weixin.qq.com/cgi-bin/token?secret=abc123');
 *   // => https://api.weixin.qq.com/cgi-bin/token?secret=ab****23
 */
final class LogSanitizer
{
    /**
     * 需要脱敏的字段名（小写，支持子串匹配）
     *
     * @var array<int, string>
     */
    public const array SENSITIVE_KEYS = [
        'secret',
        'app_secret',
        'appsecret',
        'corpsecret',
        'client_secret',
        'access_token',
        'component_access_token',
        'authorizer_access_token',
        'refresh_token',
        'tenant_access_token',
        'session_key',
        'private_key',
        'encoding_aes_key',
        'sign',
        'authorization',
        'password',
        'ticket',
        'code',
        'sk',
    ];

    /**
     * 掩码保留的首尾明文长度
     */
    public const int VISIBLE_LENGTH = 2;

    /**
     * 脱敏 URL（含 query string）
     */
    public static function uri(string $uri): string
    {
        $parts = parse_url($uri);
        if ($parts === false || !isset($parts['query']) || $parts['query'] === '') {
            return $uri;
        }

        parse_str($parts['query'], $query);
        $scrubbed = self::arrayValues($query);

        $rebuilt = str_replace(
            '?' . $parts['query'],
            '?' . http_build_query($scrubbed),
            $uri
        );

        return $rebuilt;
    }

    /**
     * 脱敏请求头
     *
     * @param array<string, array<int, string>|string> $headers
     * @return array<string, array<int, string>|string>
     */
    public static function headers(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            if (!self::isSensitive((string) $name)) {
                $result[$name] = $value;
                continue;
            }

            $result[$name] = is_array($value)
                ? array_map(static fn (string $item): string => self::mask($item), $value)
                : self::mask((string) $value);
        }

        return $result;
    }

    /**
     * 递归脱敏数组
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function arrayValues(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::arrayValues($value);
                continue;
            }

            if (self::isSensitive((string) $key) && is_scalar($value)) {
                $result[$key] = self::mask((string) $value);
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * 判断字段名是否敏感
     */
    public static function isSensitive(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 生成掩码字符串，保留首尾各 2 位便于排障比对
     */
    public static function mask(string $value): string
    {
        $length = mb_strlen($value);
        if ($length === 0) {
            return '';
        }

        if ($length <= self::VISIBLE_LENGTH * 2) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, self::VISIBLE_LENGTH)
            . '****'
            . mb_substr($value, -self::VISIBLE_LENGTH);
    }
}
