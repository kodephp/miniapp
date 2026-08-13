<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Dingtalk;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 钉钉用户资料适配器
 */
final class DingtalkUserAdapter extends BaseAdapter implements UserAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::Dingtalk;
    }

    #[\Override]
    public function profile(string $openId, array $payload = []): UnionUser
    {
        $provider = $this->provider('dingtalk');
        $app      = $provider->app();
        if (!$app instanceof DingtalkApp) {
            throw new \RuntimeException('钉钉用户资料要求 dingtalk Provider');
        }

        $raw = $app->auth()->userDetail($openId);

        return UnionUser::fromRaw(
            channel: Channel::Dingtalk,
            openId:  $openId,
            unionId: '',
            raw:     $raw,
        );
    }
}
