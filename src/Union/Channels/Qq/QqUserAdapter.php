<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Qq;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Qq\QqApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * QQ 用户资料适配器
 */
final class QqUserAdapter extends BaseAdapter implements UserAdapter
{
    public function channel(): Channel
    {
        return Channel::Qq;
    }

    public function profile(string $openId, array $payload = []): UnionUser
    {
        $provider = $this->provider('qq');
        $app      = $provider->app();
        if (!$app instanceof QqApp) {
            throw new \RuntimeException('QQ 用户资料要求 qq Provider');
        }

        return UnionUser::fromRaw(
            channel: Channel::Qq,
            openId:  $openId,
            unionId: '',
            raw:     [],
        );
    }
}
