<?php

declare(strict_types=1);

namespace Kode\MiniApp\Utils;

/**
 * 字符串工具类
 */
final class Str
{
    /**
     * 生成随机字符串
     */
    public static function random(int $length = 16): string
    {
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        $max    = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * 生成 UUID v4
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 下划线转驼峰
     */
    public static function camel(string $str): string
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }

    /**
     * 驼峰转下划线
     */
    public static function snake(string $str): string
    {
        $replaced = preg_replace('/([a-z])([A-Z])/', '$1_$2', $str);

        return strtolower($replaced ?? $str);
    }
}
