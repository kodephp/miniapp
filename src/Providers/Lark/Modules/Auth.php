<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书认证模块
 *
 * tenant_access_token 默认走缓存（PSR-16），带缓存击穿保护。
 * 飞书返回的有效期字段为 expire（秒）。
 */
readonly class Auth
{
    private const string TOKEN_SCOPE = 'tenant_access_token';

    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 获取 Tenant Access Token（默认命中缓存）
     */
    public function token(bool $forceRefresh = false): string
    {
        $manager = TokenManager::for($this->app->config());

        $token = $forceRefresh
            ? $manager->refresh(Platform::Lark, $this->identity(), self::TOKEN_SCOPE, $this->resolver())
            : $manager->remember(Platform::Lark, $this->identity(), self::TOKEN_SCOPE, $this->resolver());

        return is_string($token) ? $token : '';
    }

    /**
     * 强制刷新 Tenant Access Token
     */
    public function refreshToken(): string
    {
        return $this->token(true);
    }

    /**
     * 清除 Tenant Access Token 缓存
     */
    public function forgetToken(): void
    {
        TokenManager::for($this->app->config())
            ->forget(Platform::Lark, $this->identity(), self::TOKEN_SCOPE);
    }

    /**
     * 通过 code 获取用户信息
     *
     * @return array<string, mixed>
     */
    public function user(string $code): array
    {
        $token    = $this->token();
        $response = $this->app->http()->postJson(
            $this->baseUrl() . '/open-apis/authen/v1/access_token',
            ['grant_type' => 'authorization_code', 'code' => $code],
            ['Authorization' => "Bearer {$token}"]
        );

        /** @var array<string, mixed> */
        return ApiResponse::fromPsr($response, Platform::Lark)
            ->throwIfFailed('获取用户信息')
            ->array('data');
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
            $this->baseUrl() . "/open-apis/contact/v3/users/{$userId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        /** @var array<string, mixed> */
        return ApiResponse::fromPsr($response, Platform::Lark)
            ->throwIfFailed('获取用户详情')
            ->array('data');
    }

    private function baseUrl(): string
    {
        $config = $this->app->config();
        $custom = $config->get('base_url');

        return is_string($custom) && $custom !== '' ? $custom : $config->baseUrl();
    }

    private function identity(): string
    {
        $config = $this->app->config();

        return $config->appId() . '|' . $config->secret();
    }

    /**
     * @return callable(): TokenResult
     */
    private function resolver(): callable
    {
        return function (): TokenResult {
            $config   = $this->app->config();
            $response = $this->app->http()->postJson(
                $this->baseUrl() . '/open-apis/auth/v3/tenant_access_token/internal',
                [
                    'app_id'     => $config->appId(),
                    'app_secret' => $config->secret(),
                ]
            );

            $api = ApiResponse::fromPsr($response, Platform::Lark)
                ->throwIfFailed('获取 AccessToken');

            return new TokenResult(
                $api->string('tenant_access_token'),
                $api->int('expire', TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
    }
}
