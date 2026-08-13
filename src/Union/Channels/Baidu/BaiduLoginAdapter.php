<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Baidu;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Baidu\BaiduApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 百度智能小程序登录适配器
 *
 * 业务流程：
 *   1. 客户端 swan.login 拿到 code
 *   2. 服务端调 /public/2.0/smartapp/auth/tp/token 拿 openid / unionid
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::BaiduMini, ['code' => 'xxx']);
 */
final class BaiduLoginAdapter extends BaseAdapter implements LoginAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::BaiduMini;
    }

    #[\Override]
    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider = $this->provider('baidu');
        $app      = $provider->app();
        if (!$app instanceof BaiduApp) {
            throw new \RuntimeException('百度登录要求 baidu Provider');
        }

        $session = $app->auth()->session($code);

        $openId  = self::str($session, 'open_id');
        $unionId = self::strOrNull($session, 'unionid') ?? '';

        return UnionUser::fromRaw(
            channel: Channel::BaiduMini,
            openId:  $openId,
            unionId: $unionId,
            raw:     $session,
            extra:   $session,
        );
    }
}
