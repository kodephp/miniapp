<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

/**
 * 微信运行环境判定助手（基于 User-Agent）
 *
 * 用途：业务侧按 UA 分流微信登录通道——
 *   - 微信内置浏览器（含企业微信）→ 公众号网页授权（Union::authorizeUrl + WechatMp/WechatH5）
 *   - 微信外浏览器 → 开放平台扫码（Union::qrConnectUrl + WechatPc）
 *
 * 判定口径（大小写不敏感）：
 *   - isWechatBrowser：UA 含 MicroMessenger（微信内置）或 wxwork（企业微信内置）
 *   - isWechatWork：UA 含 wxwork（企业微信）
 */
final class WechatEnv
{
    private const UA_WECHAT = 'micromessenger';
    private const UA_WORK   = 'wxwork';

    /**
     * 是否微信内置浏览器（含企业微信内置浏览器）
     */
    public static function isWechatBrowser(string $userAgent): bool
    {
        return self::contains($userAgent, self::UA_WECHAT)
            || self::contains($userAgent, self::UA_WORK);
    }

    /**
     * 是否企业微信内置浏览器
     */
    public static function isWechatWork(string $userAgent): bool
    {
        return self::contains($userAgent, self::UA_WORK);
    }

    private static function contains(string $userAgent, string $needle): bool
    {
        return str_contains(strtolower($userAgent), $needle);
    }
}
