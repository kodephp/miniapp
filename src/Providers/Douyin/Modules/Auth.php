<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Douyin\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Providers\Douyin\DouyinApp;

/**
 * 抖音登录/授权模块
 *
 * AccessToken 默认走缓存（PSR-16），带缓存击穿保护。
 * 抖音的错误字段为 err_no / err_tips，业务数据挂在 data 节点。
 */
readonly class Auth
{
    private const string BASE_URL    = 'https://developer.toutiao.com/api/apps';
    private const string TOKEN_SCOPE = 'access_token';

    public function __construct(
        private DouyinApp $app,
    ) {
    }

    /**
     * 小程序登录，获取 session
     *
     * @return array<string, mixed>
     */
    public function session(string $code, string $anonymousCode = ''): array
    {
        $config = $this->app->config();

        $response = $this->app->http()->get(self::BASE_URL . '/v2/jscode2session', [
            'query' => [
                'appid'          => $config->appId(),
                'secret'         => $config->secret(),
                'code'           => $code,
                'anonymous_code' => $anonymousCode,
            ],
        ]);

        /** @var array<string, mixed> */
        return ApiResponse::fromPsr($response, Platform::Douyin)
            ->throwIfFailed('抖音登录')
            ->array('data');
    }

    /**
     * 拉取用户资料（昵称 / 头像 / 性别 / union_id）
     *
     * 抖音小程序资料接口使用 app access_token（服务端令牌）+ openid。
     * 未传 access_token 时自动获取服务端令牌。
     *
     * @return array<string, mixed>
     */
    public function userInfo(string $openId, string $accessToken = ''): array
    {
        $token = $accessToken !== '' ? $accessToken : $this->token();

        $response = $this->app->http()->post(self::BASE_URL . '/v2/user/get_profile', [
            'form_params' => [
                'access_token' => $token,
                'openid'       => $openId,
            ],
        ]);

        /** @var array<string, mixed> */
        return ApiResponse::fromPsr($response, Platform::Douyin)
            ->throwIfFailed('抖音获取用户信息')
            ->array('data');
    }

    /**
     * 获取 AccessToken（默认命中缓存）
     */
    public function token(bool $forceRefresh = false): string
    {
        $manager = TokenManager::for($this->app->config());

        $token = $forceRefresh
            ? $manager->refresh(Platform::Douyin, $this->identity(), self::TOKEN_SCOPE, $this->resolver())
            : $manager->remember(Platform::Douyin, $this->identity(), self::TOKEN_SCOPE, $this->resolver());

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
            ->forget(Platform::Douyin, $this->identity(), self::TOKEN_SCOPE);
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
            $response = $this->app->http()->get(self::BASE_URL . '/v2/token', [
                'query' => [
                    'appid'      => $config->appId(),
                    'secret'     => $config->secret(),
                    'grant_type' => 'client_credential',
                ],
            ]);

            $api = ApiResponse::fromPsr($response, Platform::Douyin)
                ->throwIfFailed('获取 AccessToken');

            return new TokenResult(
                $api->string('data.access_token'),
                $api->int('data.expires_in', TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
    }
}
