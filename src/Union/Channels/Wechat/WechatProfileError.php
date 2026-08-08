<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Exceptions\ApiException;

/**
 * 微信用户资料接口的错误判定与统一抛错
 *
 * 微信 cgi-bin/user/info 与 sns/userinfo 在 errcode != 0 时含义不同：
 *  - 48001：用户未关注公众号 / 未授权 snsapi_userinfo —— 属于业务常态，
 *    应降级为「空资料」而非抛错（与微信生态的订阅制一致）。
 *  - 其余（40001 令牌失效、40003 openid 非法、50001 未授权、40013 appid 错误等）
 *    属于真实错误，必须与支付宝 / 抖音 / QQ 等平台一致地抛出 ApiException，
 *    杜绝静默失败（此前这些错误被吞进占位 UnionUser，业务侧无法感知）。
 *
 * 该判定被微信系所有资料拉取路径（公众号 mp、开放平台 App / PC）复用，
 * 保证失败语义在整个 SDK 内一致。
 */
final class WechatProfileError
{
    /**
     * 预期内、可降级为「空资料」的错误码（不抛异常）
     */
    public const int BENIGN_NO_USERINFO = 48001;

    /**
     * 是否为「预期内空资料」错误（用户未关注 / 未授权 userinfo）
     */
    public static function isBenign(int $errcode): bool
    {
        return $errcode === self::BENIGN_NO_USERINFO;
    }

    /**
     * 资料接口返回真实错误时统一抛出 ApiException（48001 预期内错误不抛）
     *
     * @param array<string, mixed> $raw
     * @throws ApiException
     */
    public static function throwOnGenuine(int $errcode, array $raw, string $action): void
    {
        if ($errcode === 0 || self::isBenign($errcode)) {
            return;
        }

        throw new ApiException(
            message: is_scalar($raw['errmsg'] ?? null) ? (string) $raw['errmsg'] : '微信用户资料接口返回错误',
            errorCode: $errcode,
            platform: Platform::Wechat,
            payload: $raw,
            action: $action,
        );
    }
}
