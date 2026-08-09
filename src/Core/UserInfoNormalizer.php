<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

/**
 * 用户资料（userInfo）输出归一化
 *
 * 各端加密型 userInfo（encryptedData 解密）明文结构均兼容微信 getUserInfo：
 * `nickName` / `avatarUrl` / `gender` / `city` / `province` / `country` / `language`
 * / `watermark`。本工具把这些字段归一化为稳定的 snake_case canonical 键名
 * （`nickname` / `avatar` / `gender` / `city` / `province` / `country` / `language`），
 * 与登录 / profile 链路的 {@see \Kode\MiniApp\Union\UnionUser} 字段命名保持一致，
 * 便于业务侧以统一结构消费。
 *
 * 归一化规则：
 *   - nickname ← nickName | nickname | nick_name | name
 *   - avatar   ← avatarUrl | avatar | avatar_url | headimgurl
 *   - gender   ← gender（值原样透传，不做枚举映射，避免臆测各端编码差异）
 *   - city / province / country / language ← 同名键原样透传
 *
 * 设计：纯函数、无副作用，不依赖任何平台配置；缺失字符串字段填空串，gender 缺失为 null，绝不抛异常。
 */
final class UserInfoNormalizer
{
    /**
     * @param array<string, mixed> $raw 原始用户资料数组（encryptedData 解密结果）
     *
     * @return array{nickname:string, avatar:string, gender:mixed, city:string, province:string, country:string, language:string}
     */
    public static function normalize(array $raw): array
    {
        $nickname = self::pick($raw, ['nickName', 'nickname', 'nick_name', 'name']) ?? '';
        $avatar   = self::pick($raw, ['avatarUrl', 'avatar', 'avatar_url', 'headimgurl']) ?? '';
        $gender   = $raw['gender'] ?? null;
        $city     = is_string($raw['city'] ?? null) ? $raw['city'] : '';
        $province = is_string($raw['province'] ?? null) ? $raw['province'] : '';
        $country  = is_string($raw['country'] ?? null) ? $raw['country'] : '';
        $language = is_string($raw['language'] ?? null) ? $raw['language'] : '';

        return [
            'nickname'  => $nickname,
            'avatar'    => $avatar,
            'gender'    => $gender,
            'city'      => $city,
            'province'  => $province,
            'country'   => $country,
            'language'  => $language,
        ];
    }

    /**
     * 取第一个存在的非空字符串字段
     *
     * @param array<string, mixed> $raw
     * @param list<string>         $keys
     */
    private static function pick(array $raw, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
