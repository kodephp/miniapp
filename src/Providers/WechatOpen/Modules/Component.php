<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
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
     * 该 token 有效期 2 小时。本方法走 PSR-16 缓存（带单飞保护），
     * 首次用 ticket 换取并缓存，后续调用直接命中缓存、不再请求微信，
     * 避免高频重复换取触发微信每日配额限制。
     *
     * @return array<string, mixed>
     */
    public function accessToken(string $verifyTicket): array
    {
        $manager  = TokenManager::for($this->app->config());
        $identity = $this->app->config()->componentAppId();

        $token = $manager->remember(
            Platform::WechatOpen,
            $identity,
            'component_access_token',
            function () use ($verifyTicket): TokenResult {
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
                $data = is_array($data) ? $data : [];

                if (empty($data['component_access_token'])) {
                    throw new \RuntimeException(
                        'component_access_token 获取失败: ' . (string) ($data['errmsg'] ?? '未知错误')
                    );
                }

                return new TokenResult(
                    $data['component_access_token'],
                    (int) ($data['expires_in'] ?? TokenResult::DEFAULT_EXPIRES_IN)
                );
            },
        );

        return [
            'component_access_token' => is_string($token) ? $token : '',
            'expires_in'             => TokenResult::DEFAULT_EXPIRES_IN,
        ];
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

        // 真实对接：非法 JSON 归一化为空数组；微信错误（errcode）由上层适配器判定。
        return ApiResponse::fromPsr($response, Platform::Wechat)->toArray();
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
     * 获取 / 复用授权方 access_token（默认命中缓存，带单飞保护）
     *
     * authorizer_access_token 有效期 2 小时，应通过本方法复用，避免重复刷新。
     * 刷新需 component_access_token 与 authorizer_refresh_token。
     *
     * @param bool $forceRefresh 是否忽略缓存强制刷新
     */
    public function authorizerAccessToken(
        string $componentAccessToken,
        string $authorizerAppId,
        string $authorizerRefreshToken,
        bool $forceRefresh = false,
    ): string {
        $manager = TokenManager::for($this->app->config());

        $token = $forceRefresh
            ? $manager->refresh(
                Platform::WechatOpen,
                $authorizerAppId,
                'authorizer_access_token',
                $this->authorizerTokenResolver($componentAccessToken, $authorizerAppId, $authorizerRefreshToken),
            )
            : $manager->remember(
                Platform::WechatOpen,
                $authorizerAppId,
                'authorizer_access_token',
                $this->authorizerTokenResolver($componentAccessToken, $authorizerAppId, $authorizerRefreshToken),
            );

        return is_string($token) ? $token : '';
    }

    /**
     * @return callable(): TokenResult
     */
    private function authorizerTokenResolver(
        string $componentAccessToken,
        string $authorizerAppId,
        string $authorizerRefreshToken,
    ): callable {
        return function () use ($componentAccessToken, $authorizerAppId, $authorizerRefreshToken): TokenResult {
            $data = $this->refreshAuthorizerToken(
                $componentAccessToken,
                $authorizerAppId,
                $authorizerRefreshToken
            );

            if (empty($data['authorizer_access_token'])) {
                throw new \RuntimeException(
                    'authorizer_access_token 刷新失败: ' . (string) ($data['errmsg'] ?? '未知错误')
                );
            }

            return new TokenResult(
                $data['authorizer_access_token'],
                (int) ($data['expires_in'] ?? TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
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
