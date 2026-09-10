<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信公众号网页授权模块（OAuth2 authorize URL 生成）
 *
 * 覆盖「公众号网页授权」第一步 —— 生成引导用户跳转微信授权页的 URL：
 *   https://open.weixin.qq.com/connect/oauth2/authorize?appid=..&redirect_uri=..
 *   &response_type=code&scope=..&state=..#wechat_redirect
 *
 * 用户在微信内置浏览器确认授权后，微信回调 redirect_uri 并附上 code，
 * 业务侧再拿 code 走 {@see \Kode\MiniApp\Union\Channels\Wechat\MpLoginAdapter}
 * （即 `Union::authenticate(Channel::WechatMp, ['code' => $code])`）完成登录。
 *
 * 注意：本端点仅在微信内置浏览器内有效；PC 端扫码登录（snsapi_login）请使用
 * 开放平台网站应用的 connect/qrconnect 端点
 * （{@see \Kode\MiniApp\Providers\WechatOpen\Modules\OpenApp::qrConnectUrl()}，
 * Union 门面经 `Union::qrConnectUrl(Channel::WechatPc, ...)` 暴露）。
 *
 * 与 {@see OpenApp::qrConnectUrl()} 同构：纯 URL 拼装，不发起 HTTP 请求。
 */
final readonly class Oauth
{
    /**
     * 公众号网页授权端点（微信官方文档：网页授权）
     */
    private const API_AUTHORIZE = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    /**
     * 静默授权：不弹出授权页，只能拿到 openid
     */
    public const SCOPE_BASE = 'snsapi_base';

    /**
     * 弹出授权页：可拿到 openid + 用户昵称 / 头像等资料
     */
    public const SCOPE_USERINFO = 'snsapi_userinfo';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 构造公众号网页授权 URL
     *
     * 业务侧用法（Union 门面已封装 appId 自动取值，推荐直接用）：
     *   $url = $kernel->union()->authorizeUrl(
     *       Channel::WechatMp,
     *       'https://biz.example.com/wechat/callback',
     *       'snsapi_userinfo',
     *       'csrf-state-xyz',
     *   );
     *   // 302 跳转 $url，微信回调后用 code 走 authenticate()
     *
     * @param string $appId          公众号 appId（留空时从配置读取）
     * @param string $redirectUri    授权回调地址（须在公众号后台配置网页授权域名，本方法自动 urlencode）
     * @param string $scope          snsapi_base（静默）/ snsapi_userinfo（弹窗），非法值大声失败
     * @param string $state          防 CSRF 随机串，微信原样回传
     * @param bool   $wechatRedirect 是否追加 #wechat_redirect 锚点（微信官方要求携带）
     * @param array<string, string> $extra 额外查询参数（如自定义 lang 等）
     *
     * @throws \InvalidArgumentException scope 非法时抛出（大声失败，不静默拼接）
     */
    public function authorizeUrl(
        string $appId,
        string $redirectUri,
        string $scope = self::SCOPE_BASE,
        string $state = 'state',
        bool $wechatRedirect = true,
        array $extra = [],
    ): string {
        if ($scope !== self::SCOPE_BASE && $scope !== self::SCOPE_USERINFO) {
            throw new \InvalidArgumentException(
                "公众号网页授权 scope 仅支持 " . self::SCOPE_BASE . ' / ' . self::SCOPE_USERINFO
                . "，收到 [{$scope}]（PC 扫码登录请改用开放平台 qrconnect 端点）",
            );
        }

        if ($appId === '') {
            $appId = $this->app->config()->appId();
        }

        $query = array_merge([
            'appid'         => $appId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $scope,
            'state'         => $state,
        ], $extra);

        $url = self::API_AUTHORIZE . '?' . http_build_query($query);

        return $wechatRedirect ? $url . '#wechat_redirect' : $url;
    }
}
