<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信小程序登录适配器
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::WechatMini, ['code' => 'xxx']);
 *
 * 内部实现：
 *   1. 调用 jscode2session 拿 session_key / openid / unionid
 *   2. 构造 UnionUser 统一对象
 *
 * 注意：小程序返回的 session_key 属于敏感凭证，不应返回给前端。
 */
final class MiniLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatMini;
    }

    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        /** @var PlatformInterface $provider */
        $provider = $this->provider('wechat');
        $app      = $provider->app();
        if (!$app instanceof WechatApp) {
            throw new \RuntimeException('微信小程序登录要求 wechat Provider');
        }
        $session = $app->auth()->session($code);

        $openId  = self::str($session, 'openid');
        $unionId = self::strOrNull($session, 'unionid') ?? '';

        return UnionUser::fromRaw(
            channel: Channel::WechatMini,
            openId:  $openId,
            unionId: $unionId,
            raw:     $session,
            extra:   $session,
        );
    }
}
