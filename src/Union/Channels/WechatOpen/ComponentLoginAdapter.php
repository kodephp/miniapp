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
 * 微信开放平台（第三方平台）代公众号 / 小程序登录适配器
 *
 * 业务流程：
 *   1. 公众号 / 小程序管理员在授权页同意授权，回调业务方
 *   2. 业务方拿 authorization_code 换 authorizer_access_token
 *   3. 业务方可用此 token 代公众号 / 小程序调用接口
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::WechatOpen, [
 *       'authorization_code' => 'xxx',
 *       'component_access_token' => 'COMP_TOK_001',
 *   ]);
 */
final class ComponentLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatOpen;
    }

    public function authenticate(array $payload): UnionUser
    {
        $authCode           = self::requireString($payload, 'authorization_code');
        $componentAccessTkn = self::requireString($payload, 'component_access_token');

        $provider = $this->provider('wechatOpen');
        $app      = $provider->app();
        if (!$app instanceof WechatOpenApp) {
            throw new \RuntimeException('开放平台登录要求 wechat_open Provider');
        }

        $component    = $app->component();
        $authRaw      = $component->queryAuth($componentAccessTkn, $authCode);

        // 真实对接：微信返回 errcode（如 40013 无效 appid、40001 无效 token）
        // 时必须抛错，避免授权失败静默落入空的 UnionUser。
        if (isset($authRaw['errcode']) && (int) $authRaw['errcode'] !== 0) {
            throw new \RuntimeException(
                '开放平台授权码换取失败: ' . self::str($authRaw, 'errmsg')
                . ' (' . self::str($authRaw, 'errcode') . ')'
            );
        }

        $authInfo     = is_array($authRaw['authorization_info'] ?? null) ? $authRaw['authorization_info'] : [];
        $appId        = self::str($authInfo, 'authorizer_appid');

        return new UnionUser(
            unionId: '',
            openId:  $appId,
            channel: Channel::WechatOpen,
            raw:     $authRaw,
            extra:   $authInfo,
        );
    }
}
