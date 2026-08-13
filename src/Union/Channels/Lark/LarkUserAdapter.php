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
    #[\Override]
    public function channel(): Channel
    {
        return Channel::Lark;
    }

    #[\Override]
    public function profile(string $openId, array $payload = []): UnionUser
    {
        $provider = $this->provider('lark');
        $app      = $provider->app();
        if (!$app instanceof LarkApp) {
            throw new \RuntimeException('飞书用户资料要求 lark Provider');
        }

        $raw = $this->normalize($app->auth()->userDetail($openId));

        return UnionUser::fromRaw(
            channel: Channel::Lark,
            openId:  $openId,
            unionId: self::strOrNull($raw, 'union_id') ?? '',
            raw:     $raw,
        );
    }

    /**
     * 飞书 contact/v3/users 返回的资料中，name / avatar 为嵌套对象：
     *   name:   { zh_cn, en_us, ... }
     *   avatar: { avatar_origin, avatar_240, avatar_72, ... }
     * 归一成 fromRaw 能识别的扁平 nick_name / avatar_url，避免昵称 / 头像被静默丢失。
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        $name = $raw['name'] ?? null;
        if (is_array($name)) {
            $nickname = $name['zh_cn'] ?? $name['en_us'] ?? null;
            if (is_string($nickname) && $nickname !== '') {
                $raw['nick_name'] = $nickname;
            }
        }

        $avatar = $raw['avatar'] ?? null;
        if (is_array($avatar)) {
            $url = $avatar['avatar_origin'] ?? $avatar['avatar_240'] ?? $avatar['avatar_72'] ?? null;
            if (is_string($url) && $url !== '') {
                $raw['avatar_url'] = $url;
            }
        }

        return $raw;
    }
}
