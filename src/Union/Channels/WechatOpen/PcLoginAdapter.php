<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\WechatOpen;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信 PC 网站应用扫码登录适配器
 *
 * 业务流程：
 *   1. 前端展示二维码（基于 WechatOpenApp::openApp::qrConnectUrl 生成）
 *   2. 用户扫码授权后，微信回调 redirect_uri 并附上 code
 *   3. 本适配器拿 code 换 access_token，构造 UnionUser
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::WechatPc, ['code' => 'xxx']);
 */
final class PcLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatPc;
    }

    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider = $this->provider('wechatOpen');
        $app      = $provider->app();
        if (!$app instanceof WechatOpenApp) {
            throw new \RuntimeException('PC 扫码登录要求 wechat_open Provider');
        }

        $openApp = $app->openApp();
        $token   = $openApp->accessToken($code);

        $accessToken = self::str($token, 'access_token');
        $openId      = self::str($token, 'openid');
        $unionId     = self::strOrNull($token, 'unionid') ?? '';

        $raw = $accessToken !== '' && $openId !== '' ? $openApp->userInfo($accessToken, $openId) : [];

        return UnionUser::fromRaw(
            channel: Channel::WechatPc,
            openId:  $openId,
            unionId: $unionId,
            raw:     $raw,
            extra:   $token,
        );
    }
}
