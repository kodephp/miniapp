<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Baidu;

use Kode\MiniApp\Providers\Baidu\BaiduApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 百度用户资料适配器
 */
final class BaiduUserAdapter extends BaseAdapter implements UserAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::BaiduMini;
    }

    #[\Override]
    public function profile(string $openId, array $payload = []): UnionUser
    {
        $provider = $this->provider('baidu');
        $app      = $provider->app();
        if (!$app instanceof BaiduApp) {
            throw new \RuntimeException('百度用户资料要求 baidu Provider');
        }

        $accessToken = is_string($payload['access_token'] ?? null) ? $payload['access_token'] : '';

        $raw = $accessToken !== '' ? $app->auth()->userInfo($openId, $accessToken) : [];

        return UnionUser::fromRaw(
            channel: Channel::BaiduMini,
            openId:  $openId,
            unionId: '',
            raw:     $raw,
        );
    }
}
