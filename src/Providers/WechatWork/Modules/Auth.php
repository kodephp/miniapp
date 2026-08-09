<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信认证模块
 *
 * AccessToken 默认走缓存（PSR-16），带缓存击穿保护。
 * 缓存键包含 corpid + secret + agentid，多应用互不串号。
 */
readonly class Auth
{
    private const string BASE_URL    = 'https://qyapi.weixin.qq.com/cgi-bin';
    private const string TOKEN_SCOPE = 'access_token';
    private const string SESSION_URL = 'https://qyapi.weixin.qq.com/cgi-bin/miniprogram/jscode2session';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 获取 AccessToken（默认命中缓存）
     */
    public function token(bool $forceRefresh = false): string
    {
        $manager = TokenManager::for($this->app->config());

        $token = $forceRefresh
            ? $manager->refresh(Platform::WechatWork, $this->identity(), self::TOKEN_SCOPE, $this->resolver())
            : $manager->remember(Platform::WechatWork, $this->identity(), self::TOKEN_SCOPE, $this->resolver());

        return is_string($token) ? $token : '';
    }

    /**
     * 强制刷新 AccessToken
     */
    public function refreshToken(): string
    {
        return $this->token(true);
    }

    /**
     * 清除 AccessToken 缓存
     */
    public function forgetToken(): void
    {
        TokenManager::for($this->app->config())
            ->forget(Platform::WechatWork, $this->identity(), self::TOKEN_SCOPE);
    }

    /**
     * 获取用户信息（通过 code）
     *
     * @return array<string, mixed>
     */
    public function user(string $code): array
    {
        $token    = $this->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/user/getuserinfo?access_token={$token}&code={$code}"
        );

        return ApiResponse::fromPsr($response, Platform::WechatWork)
            ->throwIfFailed('获取用户信息')
            ->toArray();
    }

    /**
     * 获取用户详情（通过 user_id）
     *
     * @return array<string, mixed>
     */
    public function userDetail(string $userId): array
    {
        $token    = $this->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/user/get?access_token={$token}&userid={$userId}"
        );

        return ApiResponse::fromPsr($response, Platform::WechatWork)
            ->throwIfFailed('获取用户详情')
            ->toArray();
    }

    /**
     * 小程序登录（企业微信小程序 jscode2session）
     *
     * 与「企业内部应用」的 {@see self::user()} 不同：本方法面向企业微信小程序，
     * 用 code 换取 session_key（用于解密客户端回传的 encryptedData），同时返回 openid / userid。
     * 登录成功后会自动把 session_key 按 openid 托管到 SessionKeyManager，供后续一站式解密复用。
     *
     * @return array<string, mixed> 含 session_key / openid / userid / expires_in
     */
    public function session(string $code): array
    {
        $config = $this->app->config();
        $token  = $this->token();

        $response = $this->app->http()->get(self::SESSION_URL, [
            'query' => [
                'access_token' => $token,
                'js_code'      => $code,
                'grant_type'   => 'authorization_code',
            ],
        ]);

        $result = ApiResponse::fromPsr($response, Platform::WechatWork)
            ->throwIfFailed('企业微信小程序登录')
            ->toArray();

        $openId     = (string) ($result['openid'] ?? '');
        $sessionKey = (string) ($result['session_key'] ?? '');
        if ($openId !== '' && $sessionKey !== '') {
            SessionKeyManager::for($config)->store($openId, $sessionKey);
        }

        return $result;
    }

    private function identity(): string
    {
        $config = $this->app->config();

        return $config->corpId() . '|' . $config->secret() . '|' . $config->agentId();
    }

    /**
     * @return callable(): TokenResult
     */
    private function resolver(): callable
    {
        return function (): TokenResult {
            $config   = $this->app->config();
            $response = $this->app->http()->get(self::BASE_URL . '/gettoken', [
                'query' => [
                    'corpid'     => $config->corpId(),
                    'corpsecret' => $config->secret(),
                ],
            ]);

            $api = ApiResponse::fromPsr($response, Platform::WechatWork)
                ->throwIfFailed('获取 AccessToken');

            return new TokenResult(
                $api->string('access_token'),
                $api->int('expires_in', TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
    }
}
