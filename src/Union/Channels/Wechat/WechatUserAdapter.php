<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Exceptions\ConfigException;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信用户资料适配器
 *
 * 通过 OpenID 拉取用户资料（"获取用户信息"环节）。
 *
 * 各渠道处理方式：
 *  - 公众号 / H5：使用 mp access_token 调用 cgi-bin/user/info（token 自动解析，无需调用方传入）
 *  - 小程序：服务端无独立用户资料接口，使用客户端上报（已解密）的数据
 *  - 开放平台移动 / 网站应用（App / PC）：使用登录时获取的 OAuth access_token 拉取
 *
 * 微信开放平台绑定后（同一开放平台下绑定公众号 / 小程序 / App / PC），
 * 各渠道登录与拉取资料都会携带相同的 unionId，业务侧可据此关联同一用户。
 */
final class WechatUserAdapter extends BaseAdapter implements UserAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatMp;
    }

    public function profile(string $openId, array $payload = []): UnionUser
    {
        $channel = isset($payload['channel']) && is_string($payload['channel'])
            ? Channel::from($payload['channel'])
            : Channel::WechatMp;

        // 小程序：服务端无独立用户资料接口，使用客户端上报（已解密）的数据
        if ($channel === Channel::WechatMini) {
            $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
            $unionId = self::strOrNull($raw, 'unionId') ?? self::strOrNull($raw, 'unionid') ?? '';
            return UnionUser::fromRaw(
                channel: $channel,
                openId:  $openId,
                unionId: $unionId,
                raw:     $raw,
            );
        }

        // 开放平台移动 / 网站应用：使用登录时获取的 OAuth access_token 拉取
        if ($channel === Channel::WechatApp || $channel === Channel::WechatPc) {
            $accessToken = is_string($payload['access_token'] ?? null) ? $payload['access_token'] : '';
            if ($accessToken !== '') {
                $raw = $this->openAppUserInfo($accessToken, $openId);
                if ($raw !== null) {
                    $unionId = self::strOrNull($raw, 'unionid') ?? '';
                    return UnionUser::fromRaw(
                        channel: $channel,
                        openId:  $openId,
                        unionId: $unionId,
                        raw:     $raw,
                    );
                }
            }
            return UnionUser::fromRaw(channel: $channel, openId: $openId);
        }

        // 仅公众号 / H5 通过 mp access_token 拉取；其余微信渠道不适用
        if ($channel !== Channel::WechatMp && $channel !== Channel::WechatH5) {
            return UnionUser::fromRaw(channel: $channel, openId: $openId);
        }

        $provider = $this->provider('wechat');
        $app = $provider->app();
        if (!$app instanceof WechatApp) {
            throw new \RuntimeException('微信用户资料要求 wechat Provider');
        }

        $raw = $app->user()->info($openId);
        if (isset($raw['errcode']) && (int) $raw['errcode'] !== 0) {
            // access_token 失效或 openid 非本公众号粉丝，返回占位并保留原始错误
            return UnionUser::fromRaw(channel: $channel, openId: $openId, raw: $raw);
        }

        $unionId = self::strOrNull($raw, 'unionid') ?? '';
        return UnionUser::fromRaw(channel: $channel, openId: $openId, unionId: $unionId, raw: $raw);
    }

    /**
     * 通过微信开放平台 Provider 拉取移动 / 网站应用用户信息
     *
     * @return array<string, mixed>|null 未配置开放平台或调用失败时返回 null
     */
    private function openAppUserInfo(string $accessToken, string $openId): ?array
    {
        try {
            $provider = $this->provider('wechatOpen');
        } catch (\RuntimeException | ConfigException) {
            return null;
        }
        $app = $provider->app();
        if (!$app instanceof WechatOpenApp) {
            return null;
        }
        return $app->openApp()->userInfo($accessToken, $openId);
    }
}
