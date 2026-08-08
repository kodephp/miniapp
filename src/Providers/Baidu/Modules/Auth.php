<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\Baidu\BaiduApp;

/**
 * 百度登录/授权模块
 *
 * AccessToken 默认走缓存（PSR-16），带缓存击穿保护。
 * 百度采用 OAuth 风格错误字段 error / error_description。
 */
readonly class Auth
{
    private const string BASE_URL    = 'https://openapi.baidu.com';
    private const string TOKEN_SCOPE = 'access_token';

    public function __construct(
        private BaiduApp $app,
    ) {
    }

    /**
     * 获取 SessionKey（小程序登录）
     *
     * @return array<string, mixed>
     */
    public function session(string $code): array
    {
        $config = $this->app->config();

        $response = $this->app->http()->get(self::BASE_URL . '/oauth/2.0/token', [
            'query' => [
                'client_id' => $config->appId(),
                'sk'        => $config->secret(),
                'code'      => $code,
            ],
        ]);

        return ApiResponse::fromPsr($response, Platform::Baidu)
            ->throwIfFailed('百度登录')
            ->toArray();
    }

    /**
     * 拉取用户资料（昵称 / 头像 / 性别）
     *
     * 百度智能小程序资料接口（openapi.baidu.com）使用用户 access_token + openid。
     * 其错误字段为 errno（非授权接口的 error），需单独判断。
     *
     * @return array<string, mixed>
     */
    public function userInfo(string $openId, string $accessToken): array
    {
        $response = $this->app->http()->get(self::BASE_URL . '/rest/2.0/smartapp/getuserinfo', [
            'query' => [
                'access_token' => $accessToken,
                'openid'       => $openId,
            ],
        ]);

        $api   = ApiResponse::fromPsr($response, Platform::Baidu);
        $errno = (int) ($api['errno'] ?? 0);
        if ($errno !== 0) {
            throw new ApiException(
                message:   (string) ($api['msg'] ?? '获取用户信息失败'),
                errorCode: $errno,
                platform:  Platform::Baidu,
                payload:   $api->toArray(),
                action:    '百度获取用户信息',
            );
        }

        return $api->array('data');
    }

    /**
     * 获取 AccessToken（服务端，默认命中缓存）
     *
     * @return array<string, mixed>
     */
    public function token(bool $forceRefresh = false): array
    {
        $manager = TokenManager::for($this->app->config());

        $token = $forceRefresh
            ? $manager->refresh(Platform::Baidu, $this->identity(), self::TOKEN_SCOPE, $this->resolver())
            : $manager->remember(Platform::Baidu, $this->identity(), self::TOKEN_SCOPE, $this->resolver());

        /** @var array<string, mixed> */
        return is_array($token) ? $token : [];
    }

    /**
     * 获取 AccessToken 字符串（便捷方法）
     */
    public function accessToken(bool $forceRefresh = false): string
    {
        $token = $this->token($forceRefresh)['access_token'] ?? '';

        return is_scalar($token) ? (string) $token : '';
    }

    /**
     * 强制刷新 AccessToken
     *
     * @return array<string, mixed>
     */
    public function refreshToken(): array
    {
        return $this->token(true);
    }

    /**
     * 清除 AccessToken 缓存
     */
    public function forgetToken(): void
    {
        TokenManager::for($this->app->config())
            ->forget(Platform::Baidu, $this->identity(), self::TOKEN_SCOPE);
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
            $response = $this->app->http()->get(self::BASE_URL . '/oauth/2.0/token', [
                'query' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $config->appId(),
                    'client_secret' => $config->secret(),
                    'scope'         => 'basic',
                ],
            ]);

            $api = ApiResponse::fromPsr($response, Platform::Baidu)
                ->throwIfFailed('获取 AccessToken');

            return new TokenResult(
                $api->toArray(),
                $api->int('expires_in', TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
    }
}
