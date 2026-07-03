<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Lark;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Lark\LarkApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 飞书登录适配器（企业内部应用 / 第三方应用）
 *
 * 业务流程：
 *   1. 飞书客户端拉起应用，拿到 code
 *   2. 服务端调 /open-apis/authen/v2/oauth/token 拿 access_token / user_id
 *   3. 调 /open-apis/contact/v3/users/{user_id} 拿用户信息
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::Lark, ['code' => 'xxx']);
 */
final class LarkLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::Lark;
    }

    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider = $this->provider('lark');
        $app      = $provider->app();
        if (!$app instanceof LarkApp) {
            throw new \RuntimeException('飞书登录要求 lark Provider');
        }

        $user = $app->auth()->user($code);

        $userId = self::str($user, 'user_id');
        $openId = self::strOrNull($user, 'open_id') ?? $userId;

        return UnionUser::fromRaw(
            channel: Channel::Lark,
            openId:  $openId,
            unionId: self::strOrNull($user, 'union_id') ?? '',
            raw:     $user,
            extra:   [],
        );
    }
}
