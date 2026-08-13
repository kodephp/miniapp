<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Qq;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Qq\QqApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * QQ 登录适配器
 *
 * 业务流程（QQ 小程序）：
 *   1. QQ 小程序客户端登录，拿到 code
 *   2. 服务端调 /sns/jscode2session 拿 session_key / openid / unionid
 *
 * QQ 与微信账号体系已互通，unionid 字段会作为跨平台账号关联依据。
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::Qq, ['code' => 'xxx']);
 */
final class QqLoginAdapter extends BaseAdapter implements LoginAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::Qq;
    }

    #[\Override]
    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider = $this->provider('qq');
        $app      = $provider->app();
        if (!$app instanceof QqApp) {
            throw new \RuntimeException('QQ 登录要求 qq Provider');
        }

        $session = $app->auth()->session($code);

        $openId  = self::str($session, 'openid');
        $unionId = self::strOrNull($session, 'unionid') ?? '';

        return UnionUser::fromRaw(
            channel: Channel::Qq,
            openId:  $openId,
            unionId: $unionId,
            raw:     $session,
            extra:   $session,
        );
    }
}
