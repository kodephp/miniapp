<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

/**
 * 手机号输出归一化
 *
 * 各端「手机号获取」返回的字段名并不统一：微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信
 * 解密后结构为 `phoneNumber` / `purePhoneNumber` / `countryCode`；而支付宝 `my.getPhoneNumber`
 * 解密后字段为 `mobile` / `countryCode`。本工具把任意原始数组归一化为统一三元组，
 * 便于业务侧以一致结构消费。
 *
 * 归一化规则：
 *   - phoneNumber     ← phoneNumber | mobile
 *   - purePhoneNumber ← purePhoneNumber（缺失时由 phoneNumber 去掉国家码前缀推导）
 *   - countryCode     ← countryCode
 *
 * 设计：纯函数、无副作用，不依赖任何平台配置；输入缺字段时对应值为空字符串，绝不抛异常。
 */
final class PhoneNormalizer
{
    /**
     * @param array<string, mixed> $raw 原始手机号数组（解密结果或接口响应）
     *
     * @return array{phoneNumber:string, purePhoneNumber:string, countryCode:string}
     */
    public static function normalize(array $raw): array
    {
        $phoneNumber = self::pick($raw, ['phoneNumber', 'mobile']) ?? '';
        $countryCode = self::pick($raw, ['countryCode', 'country_code']) ?? '';
        $pure        = self::pick($raw, ['purePhoneNumber', 'pure_phone_number']);

        if ($pure === null && $phoneNumber !== '') {
            $pure = self::stripCountryCode($phoneNumber, $countryCode);
        }

        return [
            'phoneNumber'     => $phoneNumber,
            'purePhoneNumber' => $pure ?? '',
            'countryCode'     => $countryCode,
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

    private static function stripCountryCode(string $phone, string $code): string
    {
        if ($code === '') {
            return $phone;
        }

        $prefix = '+' . ltrim($code, '+');
        if (str_starts_with($phone, $prefix)) {
            return substr($phone, strlen($prefix));
        }

        return $phone;
    }
}
