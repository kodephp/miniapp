<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Douyin;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Douyin\DouyinApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 抖音用户资料适配器
 */
final class DouyinUserAdapter extends BaseAdapter implements UserAdapter
{
    public function channel(): Channel
    {
        return Channel::DouyinMini;
    }

    public function profile(string $openId, array $payload = []): UnionUser
    {
        $channel = isset($payload['channel']) && is_string($payload['channel'])
            ? Channel::from($payload['channel'])
            : Channel::DouyinMini;

        return UnionUser::fromRaw(
            channel: $channel,
            openId:  $openId,
            unionId: '',
            raw:     [],
        );
    }
}
