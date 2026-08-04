<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉认证模块
 *
 * AccessToken 默认走缓存（PSR-16），带缓存击穿保护。
 */
readonly class Auth
{
    private const string BASE_URL    = 'https://oapi.dingtalk.com';
    private const string TOKEN_SCOPE = 'access_token';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 获取 AccessToken（默认命中缓存）
     */
    public function token(bool $forceRefresh = false): string
    {
        $manager = TokenManager::for($this->app->config());

        $token = $forceRefresh
            ? $manager->refresh(Platform::Dingtalk, $this->identity(), self::TOKEN_SCOPE, $this->resolver())
            : $manager->remember(Platform::Dingtalk, $this->identity(), self::TOKEN_SCOPE, $this->resolver());

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
            ->forget(Platform::Dingtalk, $this->identity(), self::TOKEN_SCOPE);
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
            self::BASE_URL . "/user/getuserinfo?access_token={$token}",
            ['code' => $code]
        );

        return ApiResponse::fromPsr($response, Platform::Dingtalk)
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
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/topapi/v2/user/get?access_token={$token}",
            ['userid' => $userId]
        );

        $api = ApiResponse::fromPsr($response, Platform::Dingtalk)
            ->throwIfFailed('获取用户详情');

        /** @var array<string, mixed> */
        return $api->array('result');
    }

    private function identity(): string
    {
        $config = $this->app->config();

        return $config->appKey() . '|' . $config->appSecret();
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
                    'appkey'    => $config->appKey(),
                    'appsecret' => $config->appSecret(),
                ],
            ]);

            $api = ApiResponse::fromPsr($response, Platform::Dingtalk)
                ->throwIfFailed('获取 AccessToken');

            return new TokenResult(
                $api->string('access_token'),
                $api->int('expires_in', TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
    }
}
