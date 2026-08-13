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
 * 微信开放平台（第三方平台）代公众号 / 小程序「账号授权」适配器
 *
 * ⚠️ 重要语义说明：本适配器处理的是「第三方平台授权流程」，不是「终端用户登录」。
 * 业务流程是：
 *   1. 公众号 / 小程序管理员在授权页同意授权，回调业务方
 *   2. 业务方拿 authorization_code 换 authorizer_access_token
 *   3. 业务方可用此 token 代公众号 / 小程序调用接口
 *
 * 因此 {@see self::authenticate()} 返回的是「授权方账号」结果（authorizer_appid、
 * authorizer_access_token、authorizer_refresh_token），**不是**终端用户。
 * 这里故意不把 authorizer_appid 当成用户 openId（否则会与真实用户混淆、
 * 且在挂了 SessionManager 时建出 key 为 appid 的错误会话）。
 * 若要拿到「该小程序下的终端用户」，请调用
 * {@see \Kode\MiniApp\Providers\WechatOpen\Modules\Authorizer::miniProgramSession()}
 * 用用户登录 code 换取 openid / unionid。
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

        // 这是授权方账号信息，不是用户；openId / unionId 留空，
        // 授权方关键信息放到 extra 供后续代调用使用。
        $authInfo = is_array($authRaw['authorization_info'] ?? null) ? $authRaw['authorization_info'] : [];

        return new UnionUser(
            unionId: '',
            openId:  '',
            channel: Channel::WechatOpen,
            raw:     $authRaw,
            extra:   $authInfo,
        );
    }
}
