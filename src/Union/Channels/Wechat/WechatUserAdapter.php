<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信用户资料适配器
 *
 * 通过 OpenID 拉取用户信息。
 * 公众号、小程序、PC 端、App 端共用此适配器。
 */
final class WechatUserAdapter extends BaseAdapter implements UserAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatMini;
    }

    public function profile(string $openId, array $payload = []): UnionUser
    {
        $provider = $this->provider('wechat');
        $app      = $provider->app();
        if (!$app instanceof WechatApp) {
            throw new \RuntimeException('微信用户资料要求 wechat Provider');
        }

        // 公众号：通过 access_token + openid 拉取
        // 小程序：没有单独的 user profile，需前端 wx.getUserProfile
        // 这里优先使用公众号接口
        $accessToken = is_string($payload['access_token'] ?? null) ? $payload['access_token'] : '';
        $channel     = isset($payload['channel']) && is_string($payload['channel'])
            ? Channel::from($payload['channel'])
            : Channel::WechatMp;

        $raw = [];
        if ($accessToken !== '') {
            $url = 'https://api.weixin.qq.com/cgi-bin/user/info'
                . '?access_token=' . urlencode($accessToken)
                . '&openid=' . urlencode($openId)
                . '&lang=zh_CN';
            $response = $app->http()->get($url);
            $data     = json_decode((string) $response->getBody(), true);
            if (is_array($data)) {
                $raw = $data;
            }
        }

        $unionId = self::strOrNull($raw, 'unionid') ?? '';

        return UnionUser::fromRaw(
            channel: $channel,
            openId:  $openId,
            unionId: $unionId,
            raw:     $raw,
        );
    }
}
