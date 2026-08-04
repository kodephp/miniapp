<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信登录模块
 *
 * AccessToken 默认走缓存（PSR-16），并带缓存击穿保护，
 * 避免每次调用接口都重新换取导致触发平台每日配额限制。
 */
readonly class Auth
{
    private const string SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session';
    private const string TOKEN_URL   = 'https://api.weixin.qq.com/cgi-bin/token';
    private const string TOKEN_SCOPE = 'access_token';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 小程序登录，获取 session
     *
     * @return array<string, mixed>
     */
    public function session(string $code): array
    {
        $config = $this->app->config();

        $response = $this->app->http()->get(self::SESSION_URL, [
            'query' => [
                'appid'      => $config->appId(),
                'secret'     => $config->secret(),
                'js_code'    => $code,
                'grant_type' => 'authorization_code',
            ],
        ]);

        return ApiResponse::fromPsr($response, Platform::Wechat)
            ->throwIfFailed('微信登录')
            ->toArray();
    }

    /**
     * 获取 AccessToken（默认命中缓存）
     *
     * @param bool $forceRefresh 是否强制向微信重新换取
     */
    public function token(bool $forceRefresh = false): string
    {
        $config   = $this->app->config();
        $manager  = TokenManager::for($config);
        $identity = $config->appId() . '|' . $config->secret();

        $token = $forceRefresh
            ? $manager->refresh(Platform::Wechat, $identity, self::TOKEN_SCOPE, $this->resolver())
            : $manager->remember(Platform::Wechat, $identity, self::TOKEN_SCOPE, $this->resolver());

        return is_string($token) ? $token : '';
    }

    /**
     * 强制刷新 AccessToken（收到 40001 等令牌失效错误码时调用）
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
        $config = $this->app->config();
        TokenManager::for($config)->forget(
            Platform::Wechat,
            $config->appId() . '|' . $config->secret(),
            self::TOKEN_SCOPE
        );
    }

    /**
     * @return callable(): TokenResult
     */
    private function resolver(): callable
    {
        return function (): TokenResult {
            $config   = $this->app->config();
            $response = $this->app->http()->get(self::TOKEN_URL, [
                'query' => [
                    'grant_type' => 'client_credential',
                    'appid'      => $config->appId(),
                    'secret'     => $config->secret(),
                ],
            ]);

            $api = ApiResponse::fromPsr($response, Platform::Wechat)
                ->throwIfFailed('获取 AccessToken');

            return new TokenResult(
                $api->string('access_token'),
                $api->int('expires_in', TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
    }
}
