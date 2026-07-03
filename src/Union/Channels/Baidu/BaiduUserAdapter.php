<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Baidu;

use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 百度用户资料适配器
 */
final class BaiduUserAdapter extends BaseAdapter implements UserAdapter
{
    public function channel(): Channel
    {
        return Channel::BaiduMini;
    }

    public function profile(string $openId, array $payload = []): UnionUser
    {
        return UnionUser::fromRaw(
            channel: Channel::BaiduMini,
            openId:  $openId,
            unionId: '',
            raw:     [],
        );
    }
}
