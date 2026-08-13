<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信公众号 / H5 登录适配器（OAuth 网页授权）
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::WechatMp, ['code' => 'xxx']);
 *
 * 内部实现：
 *   1. 通过 code 换取 access_token / openid / unionid
 *   2. 拉取用户基本信息（需 snsapi_userinfo 授权）
 *   3. 构造 UnionUser 统一对象
 *
 * 真实对接：微信返回 errcode（如 40029 无效 code）时抛 ApiException。
 */
final class MpLoginAdapter extends BaseAdapter implements LoginAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatMp;
    }

    #[\Override]
    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider   = $this->provider('wechat');
        $app        = $provider->app();
        if (!$app instanceof WechatApp) {
            throw new \RuntimeException('公众号登录要求 wechat Provider');
        }
        $http       = $app->http();
        $config     = $app->config();
        $appId      = $config->appId();
        $appSecret  = $config->secret();

        // 1. code 换 access_token（真实对接：微信错误抛 ApiException）
        $tokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token'
            . '?appid=' . urlencode($appId)
            . '&secret=' . urlencode($appSecret)
            . '&code=' . urlencode($code)
            . '&grant_type=authorization_code';

        $tokenRaw = ApiResponse::fromPsr($http->get($tokenUrl), Platform::Wechat)
            ->throwIfFailed('公众号 OAuth 换取 access_token')
            ->toArray();

        $accessToken = self::str($tokenRaw, 'access_token');
        $openId      = self::str($tokenRaw, 'openid');
        $unionId     = self::strOrNull($tokenRaw, 'unionid') ?? '';

        // 2. 拉取用户信息（需 scope 为 snsapi_userinfo）
        //    snsapi_base 静默授权不会返回用户资料（接口返回 48001），此时 raw 保持为空
        $raw = [];
        if ($accessToken !== '' && $openId !== '') {
            $userUrl = 'https://api.weixin.qq.com/sns/userinfo'
                . '?access_token=' . urlencode($accessToken)
                . '&openid=' . urlencode($openId)
                . '&lang=zh_CN';
            $userRaw = ApiResponse::fromPsr($http->get($userUrl), Platform::Wechat)->toArray();
            if (!isset($userRaw['errcode']) || (int) $userRaw['errcode'] === 0) {
                $raw = $userRaw;
            }
        }

        return UnionUser::fromRaw(
            channel: Channel::WechatMp,
            openId:  $openId,
            unionId: $unionId,
            raw:     $raw,
            extra:   $tokenRaw,
        );
    }
}
