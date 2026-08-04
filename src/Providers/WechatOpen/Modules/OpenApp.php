<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen\Modules;

use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;

/**
 * 移动应用 / 网站应用模块
 *
 * 用于已绑定到开放平台的移动 App、网页应用：
 *  - 拉起微信授权（snsapi_login 网站应用）
 *  - code 换取 access_token / openid / unionid
 *  - 刷新 / 校验 access_token
 *  - 拉取用户信息
 */
readonly class OpenApp
{
    private const API_SNS = 'https://api.weixin.qq.com/sns';
    private const API_OAUTH = 'https://open.weixin.qq.com/connect/qrconnect';

    public function __construct(
        private WechatOpenApp $app,
    ) {
    }

    /**
     * 构造网站应用扫码登录 URL
     *
     * @param array<string, string> $extra 额外参数（如 state、lang）
     */
    public function qrConnectUrl(
        string $appId,
        string $redirectUri,
        string $state = 'state',
        bool $wechatRedirect = true,
        array $extra = [],
    ): string {
        $query = array_merge([
            'appid'         => $appId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'snsapi_login',
            'state'         => $state,
        ], $extra);

        $url = self::API_OAUTH . '?' . http_build_query($query);

        return $wechatRedirect ? $url . '#wechat_redirect' : $url;
    }

    /**
     * 通过 code 换取网页授权 access_token
     *
     * - 移动应用：appId / secret 必传
     * - 网站应用：appId / secret 必传
     * 留空时尝试从 WechatOpenConfig 读取（mobile_app_id / mobile_app_secret 字段）
     *
     * @return array<string, mixed>
     */
    public function accessToken(
        string $code,
        ?string $appId = null,
        ?string $secret = null,
    ): array {
        $config = $this->app->config()->all();
        $appId = $appId ?? (string) (
            $config['mobile_app_id'] ?? $config['site_app_id'] ?? $config['app_id'] ?? ''
        );
        $secret = $secret ?? (string) (
            $config['mobile_secret'] ?? $config['site_secret'] ?? $config['secret'] ?? ''
        );

        $response = $this->app->http()->get(
            self::API_SNS . '/oauth2/access_token',
            [
                'query' => [
                    'appid'      => $appId,
                    'secret'     => $secret,
                    'code'       => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 刷新 access_token
     *
     * @return array<string, mixed>
     */
    public function refreshToken(string $appId, string $refreshToken): array
    {
        $response = $this->app->http()->get(
            self::API_SNS . '/oauth2/refresh_token',
            [
                'query' => [
                    'appid'         => $appId,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 校验 access_token 有效性
     *
     * @return array<string, mixed>
     */
    public function authCheck(string $accessToken, string $openId): array
    {
        $response = $this->app->http()->get(
            self::API_SNS . '/auth',
            [
                'query' => [
                    'access_token' => $accessToken,
                    'openid'       => $openId,
                ],
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 拉取用户信息（需 snsapi_userinfo 授权）
     *
     * @return array<string, mixed>
     */
    public function userInfo(
        string $accessToken,
        string $openId,
        string $lang = 'zh_CN',
    ): array {
        $response = $this->app->http()->get(
            self::API_SNS . '/userinfo',
            [
                'query' => [
                    'access_token' => $accessToken,
                    'openid'       => $openId,
                    'lang'         => $lang,
                ],
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 移动 App 第三方登录：客户端拿到 code 后，服务端用
     * appid + secret + code 换取 access_token。
     *
     * 流程与 snsapi_login 相同。
     *
     * @return array<string, mixed>
     */
    public function mobileAccessToken(string $appId, string $secret, string $code): array
    {
        return $this->accessToken($appId, $secret, $code);
    }
}
