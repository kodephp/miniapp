<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Dingtalk;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 钉钉登录适配器（企业内部应用）
 *
 * 业务流程：
 *   1. 钉钉客户端拉起应用，拿到 code
 *   2. 服务端调 /topapi/v2/user/getuserinfo 拿 userid
 *   3. 可选：调 /topapi/v2/user/get 拿用户详情
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::Dingtalk, ['code' => 'xxx']);
 */
final class DingtalkLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::Dingtalk;
    }

    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider = $this->provider('dingtalk');
        $app      = $provider->app();
        if (!$app instanceof DingtalkApp) {
            throw new \RuntimeException('钉钉登录要求 dingtalk Provider');
        }

        $user = $app->auth()->user($code);

        $userId = self::str($user, 'userid');
        $openId = self::strOrNull($user, 'openid') ?? $userId;

        return UnionUser::fromRaw(
            channel: Channel::Dingtalk,
            openId:  $openId,
            unionId: self::strOrNull($user, 'unionid') ?? '',
            raw:     $user,
            extra:   [],
        );
    }
}
