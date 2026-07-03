<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen\Modules;

use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;

/**
 * 第三方平台自身能力模块
 *
 * 负责 component_access_token、pre_auth_code、component_login_page 等
 * 微信开放平台第三方平台接口。
 */
readonly class Component
{
    private const API_BASE = 'https://api.weixin.qq.com/cgi-bin/component';
    private const API_BASE_OPEN = 'https://api.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatOpenApp $app,
    ) {
    }

    /**
     * 获取 component_access_token
     *
     * - 第三方平台 appid
     * - 第三方平台 appsecret
     * - 微信推送的 component_verify_ticket（每 10 分钟推送一次）
     *
     * 该 token 有效期 2 小时，需缓存使用。
     *
     * @return array<string, mixed>
     */
    public function accessToken(string $verifyTicket): array
    {
        $payload = [
            'component_appid'         => $this->app->config()->componentAppId(),
            'component_appsecret'     => $this->app->config()->componentSecret(),
            'component_verify_ticket' => $verifyTicket,
        ];

        $response = $this->app->http()->postJson(
            self::API_BASE . '/api_component_token',
            $payload
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 获取预授权码 pre_auth_code
     *
     * @return array<string, mixed>
     */
    public function preAuthCode(string $componentAccessToken): array
    {
        $response = $this->app->http()->postJson(
            self::API_BASE . '/api_create_preauthcode?component_access_token=' . $componentAccessToken,
            [
                'component_appid' => $this->app->config()->componentAppId(),
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 构造第三方平台授权页 URL
     *
     * @param array<int, string> $preAuthApps 指定只允许这些 appid 授权（可选）
     */
    public function loginPage(
        string $preAuthCode,
        string $redirectUri,
        int $authType = 1,
        ?string $bizAppId = null,
        ?array $preAuthApps = null,
    ): string {
        $query = [
            'component_appid' => $this->app->config()->componentAppId(),
            'pre_auth_code'   => $preAuthCode,
            'redirect_uri'    => $redirectUri,
            'auth_type'       => $authType,
        ];

        if ($bizAppId !== null) {
            $query['biz_appid'] = $bizAppId;
        }

        $apps = $preAuthApps ?? $this->app->config()->preAuthorizeApps();
        if (!empty($apps)) {
            $query['pre_auth_apps'] = implode(',', $apps);
        }

        return 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?'
            . http_build_query($query)
            . '#wechat_redirect';
    }

    /**
     * 使用授权码换取 authorizer_access_token
     *
     * - authorization_code 由授权回调带回
     * - 缓存该 token 用于代公众号 / 小程序调用接口
     *
     * @return array<string, mixed>
     */
    public function queryAuth(string $componentAccessToken, string $authorizationCode): array
    {
        $response = $this->app->http()->postJson(
            self::API_BASE . '/api_query_auth?component_access_token=' . $componentAccessToken,
            [
                'component_appid'    => $this->app->config()->componentAppId(),
                'authorization_code'  => $authorizationCode,
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 刷新 authorizer_access_token
     *
     * @return array<string, mixed>
     */
    public function refreshAuthorizerToken(
        string $componentAccessToken,
        string $authorizerAppId,
        string $authorizerRefreshToken,
    ): array {
        $response = $this->app->http()->postJson(
            self::API_BASE . '/api_authorizer_token?component_access_token=' . $componentAccessToken,
            [
                'component_appid'          => $this->app->config()->componentAppId(),
                'authorizer_appid'         => $authorizerAppId,
                'authorizer_refresh_token' => $authorizerRefreshToken,
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 获取授权方的基本信息（账号信息、权限集、头像等）
     *
     * @return array<string, mixed>
     */
    public function authorizerInfo(
        string $componentAccessToken,
        string $authorizerAppId,
    ): array {
        $response = $this->app->http()->postJson(
            self::API_BASE . '/api_get_authorizer_info?component_access_token=' . $componentAccessToken,
            [
                'component_appid'  => $this->app->config()->componentAppId(),
                'authorizer_appid' => $authorizerAppId,
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 获取授权方的选项设置（地理位置上报、语音识别等）
     *
     * @return array<string, mixed>
     */
    public function authorizerOption(
        string $componentAccessToken,
        string $authorizerAppId,
        string $optionName,
    ): array {
        $response = $this->app->http()->postJson(
            self::API_BASE . '/api_get_authorizer_option?component_access_token=' . $componentAccessToken,
            [
                'component_appid'  => $this->app->config()->componentAppId(),
                'authorizer_appid' => $authorizerAppId,
                'option_name'      => $optionName,
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 设置授权方选项
     *
     * @return array<string, mixed>
     */
    public function setAuthorizerOption(
        string $componentAccessToken,
        string $authorizerAppId,
        string $optionName,
        string $optionValue,
    ): array {
        $response = $this->app->http()->postJson(
            self::API_BASE . '/api_set_authorizer_option?component_access_token=' . $componentAccessToken,
            [
                'component_appid'  => $this->app->config()->componentAppId(),
                'authorizer_appid' => $authorizerAppId,
                'option_name'      => $optionName,
                'option_value'     => $optionValue,
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 代公众号 / 小程序发起 API 调用（透明转发）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function callAuthorizerApi(
        string $authorizerAccessToken,
        string $path,
        array $params = [],
        string $method = 'POST',
    ): array {
        $url = self::API_BASE_OPEN . $path;
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . 'access_token=' . $authorizerAccessToken;

        $response = $method === 'GET'
            ? $this->app->http()->get($url, ['query' => $params])
            : $this->app->http()->postJson($url, $params);

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 生成 JS-SDK 签名（代公众号使用）
     *
     * @param array<string, mixed> $params
     */
    public function signForJsSdk(array $params, string $url): string
    {
        $params['url'] = $url;
        ksort($params);
        $string = urldecode(http_build_query($params));

        return sha1($string);
    }

    /**
     * 校验微信回调签名（用于 component_verify_ticket 等推送）
     */
    public function verifySignature(
        string $timestamp,
        string $nonce,
        string $encryptedMsg,
        string $msgSignature,
    ): bool {
        $token = $this->app->config()->token();

        $tmp = [$token, $timestamp, $nonce, $encryptedMsg];
        sort($tmp, SORT_STRING);
        $signature = sha1(implode('', $tmp));

        return hash_equals($signature, $msgSignature);
    }

    /**
     * 校验代公众号 / 小程序 JS-SDK 签名
     *
     * @param array<string, mixed> $params
     */
    public function verifyJsApiSignature(array $params, string $url, string $signature): bool
    {
        $params['url'] = $url;
        ksort($params);
        $string = urldecode(http_build_query($params));

        return hash_equals(sha1($string), $signature);
    }

    /**
     * 获取第三方平台所绑定 / 授权的所有小程序、公众号列表
     *
     * @return array<string, mixed>
     */
    public function authorizerList(
        string $componentAccessToken,
        int $offset = 0,
        int $count = 100,
    ): array {
        $response = $this->app->http()->postJson(
            self::API_BASE . '/api_authorizer_list?component_access_token=' . $componentAccessToken,
            [
                'component_appid' => $this->app->config()->componentAppId(),
                'offset'          => $offset,
                'count'           => $count,
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 拉取所有已授权的公众号 / 小程序列表（自动翻页）
     *
     * @return array<int, array<string, mixed>>
     */
    public function allAuthorizers(string $componentAccessToken, int $pageSize = 100): array
    {
        $result = [];
        $offset = 0;
        while (true) {
            $batch = $this->authorizerList($componentAccessToken, $offset, $pageSize);
            $items = $batch['list'] ?? [];
            if (!is_array($items) || $items === []) {
                break;
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $result[] = $item;
                }
            }
            if (count($items) < $pageSize) {
                break;
            }
            $offset += $pageSize;
        }

        return $result;
    }
}
