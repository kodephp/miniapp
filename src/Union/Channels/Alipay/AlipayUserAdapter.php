<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Alipay;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 支付宝用户资料适配器
 *
 * 调用 alipay.user.info.share 拉取用户信息
 */
final class AlipayUserAdapter extends BaseAdapter implements UserAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::AlipayMini;
    }

    #[\Override]
    public function profile(string $openId, array $payload = []): UnionUser
    {
        $provider    = $this->provider('alipay');
        $app         = $provider->app();
        if (!$app instanceof AlipayApp) {
            throw new \RuntimeException('支付宝用户资料要求 alipay Provider');
        }
        $accessToken = is_string($payload['access_token'] ?? null) ? $payload['access_token'] : '';

        $raw = $accessToken !== '' ? $app->auth()->user($accessToken) : [];

        $channel = isset($payload['channel']) && is_string($payload['channel'])
            ? Channel::from($payload['channel'])
            : Channel::AlipayMini;

        return UnionUser::fromRaw(
            channel: $channel,
            openId:  $openId,
            unionId: '',
            raw:     $raw,
        );
    }
}
