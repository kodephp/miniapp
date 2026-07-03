<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Lark;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Lark\LarkApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 飞书用户资料适配器
 */
final class LarkUserAdapter extends BaseAdapter implements UserAdapter
{
    public function channel(): Channel
    {
        return Channel::Lark;
    }

    public function profile(string $openId, array $payload = []): UnionUser
    {
        $provider = $this->provider('lark');
        $app      = $provider->app();
        if (!$app instanceof LarkApp) {
            throw new \RuntimeException('飞书用户资料要求 lark Provider');
        }

        $raw = $app->auth()->userDetail($openId);

        return UnionUser::fromRaw(
            channel: Channel::Lark,
            openId:  $openId,
            unionId: self::strOrNull($raw, 'union_id') ?? '',
            raw:     $raw,
        );
    }
}
