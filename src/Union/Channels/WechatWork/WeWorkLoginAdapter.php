<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\WechatWork;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 企业微信登录适配器
 *
 * 业务流程（企业内部应用）：
 *   1. 企业微信客户端拉起应用，拿到 code
 *   2. 服务端拿 code 调 /auth/getuserinfo 拿 userid / user_ticket
 *   3. 可选：拿 user_ticket 换 userdetail（含头像、邮箱等）
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::WechatWork, ['code' => 'xxx']);
 */
final class WeWorkLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatWork;
    }

    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider = $this->provider('wechatWork');
        $app      = $provider->app();
        if (!$app instanceof WechatWorkApp) {
            throw new \RuntimeException('企业微信登录要求 wechat_work Provider');
        }

        $user = $app->auth()->user($code);

        $userId = self::str($user, 'userid');
        $openId = self::strOrNull($user, 'openid') ?? $userId;

        return UnionUser::fromRaw(
            channel: Channel::WechatWork,
            openId:  $openId,
            unionId: self::strOrNull($user, 'unionid') ?? '',
            raw:     $user,
            extra:   [],
        );
    }
}
