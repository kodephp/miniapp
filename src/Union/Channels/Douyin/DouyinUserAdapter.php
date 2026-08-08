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

        $accessToken = is_string($payload['access_token'] ?? null) ? $payload['access_token'] : '';

        $provider = $this->provider('douyin');
        $app      = $provider->app();
        if (!$app instanceof DouyinApp) {
            throw new \RuntimeException('抖音用户资料要求 douyin Provider');
        }

        // 抖音资料接口使用 app access_token（服务端可自取），因此始终发起请求；
        // 未传 access_token 时由 Auth::userInfo 自动回退到 app token。
        $raw = $app->auth()->userInfo($openId, $accessToken);

        return UnionUser::fromRaw(
            channel: $channel,
            openId:  $openId,
            unionId: self::strOrNull($raw, 'union_id') ?? '',
            raw:     $raw,
        );
    }
}
