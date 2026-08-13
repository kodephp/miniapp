<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\WechatWork;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 企业微信用户资料适配器
 *
 * 登录阶段通过 code 调 /auth/getuserinfo 拿到 userid / openid（见 WeWorkLoginAdapter），
 * 本适配器以 userid 为键调 /user/get 拉取成员详细资料（姓名、头像、部门、职位等）。
 *
 * 说明：
 *  - 登录阶段已把 openId 规范为 userid（openid 缺失时回退 userid），故直接用 openId 查 /user/get；
 *    业务侧也可通过 payload['userid'] 显式覆盖。
 *  - /user/get 不返回 unionid，跨端关联以登录阶段 getuserinfo 的 unionid 为准，故 unionId 留空。
 */
final class WeWorkUserAdapter extends BaseAdapter implements UserAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatWork;
    }

    #[\Override]
    public function profile(string $openId, array $payload = []): UnionUser
    {
        $provider = $this->provider('wechatWork');
        $app      = $provider->app();
        if (!$app instanceof WechatWorkApp) {
            throw new \RuntimeException('企业微信用户资料要求 wechat_work Provider');
        }

        // openId 在登录阶段已规范为 userid；允许通过 payload['userid'] 显式指定
        $userId = is_string($payload['userid'] ?? null) && $payload['userid'] !== ''
            ? $payload['userid']
            : $openId;

        $raw = $app->auth()->userDetail($userId);

        return UnionUser::fromRaw(
            channel: Channel::WechatWork,
            openId:  self::str($raw, 'userid') ?: $userId,
            unionId: '',
            raw:     $raw,
        );
    }
}
