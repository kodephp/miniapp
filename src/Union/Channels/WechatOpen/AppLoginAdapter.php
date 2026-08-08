<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\WechatOpen;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信移动 App 登录适配器（开放平台移动应用）
 *
 * 业务流程：
 *   1. App 端通过微信 SDK 拉起授权，拿到 code
 *   2. 服务端拿 code 换 access_token / openid / unionid
 *   3. 拉取用户信息，构造 UnionUser
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::WechatApp, ['code' => 'xxx']);
 */
final class AppLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatApp;
    }

    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider = $this->provider('wechatOpen');
        $app      = $provider->app();
        if (!$app instanceof WechatOpenApp) {
            throw new \RuntimeException('App 登录要求 wechat_open Provider');
        }

        $openApp    = $app->openApp();
        $token      = $openApp->accessToken($code);

        $accessToken = self::str($token, 'access_token');
        $openId      = self::str($token, 'openid');
        $unionId     = self::strOrNull($token, 'unionid') ?? '';

        // 拉取用户信息（需 snsapi_userinfo 授权）；snsapi_base 静默授权
        // 微信返回 48001，此时 raw 保持为空，避免把错误体当作用户资料。
        $raw = [];
        if ($accessToken !== '' && $openId !== '') {
            $userRaw = $openApp->userInfo($accessToken, $openId);
            if (!isset($userRaw['errcode']) || (int) $userRaw['errcode'] === 0) {
                $raw = $userRaw;
            }
        }

        return UnionUser::fromRaw(
            channel: Channel::WechatApp,
            openId:  $openId,
            unionId: $unionId,
            raw:     $raw,
            extra:   $token,
        );
    }
}
