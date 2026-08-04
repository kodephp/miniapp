<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
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
