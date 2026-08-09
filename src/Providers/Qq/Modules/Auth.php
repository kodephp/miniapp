<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Qq\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\Qq\QqApp;

/**
 * QQ 登录/授权模块
 *
 * AccessToken 默认走缓存（PSR-16），带缓存击穿保护。
 */
readonly class Auth
{
    private const string BASE_URL    = 'https://api.q.qq.com';
    private const string TOKEN_SCOPE = 'access_token';

    public function __construct(
        private QqApp $app,
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

        $response = $this->app->http()->get(self::BASE_URL . '/sns/jscode2session', [
            'query' => [
                'appid'      => $config->appId(),
                'secret'     => $config->secret(),
                'js_code'    => $code,
                'grant_type' => 'authorization_code',
            ],
        ]);

        $result = ApiResponse::fromPsr($response, Platform::Qq)
            ->throwIfFailed('QQ 登录')
            ->toArray();

        $openId     = (string) ($result['openid'] ?? '');
        $sessionKey = (string) ($result['session_key'] ?? '');
        if ($openId !== '' && $sessionKey !== '') {
            SessionKeyManager::for($config)->store($openId, $sessionKey);
        }

        return $result;
    }

    /**
     * 拉取用户资料（昵称 / 头像 / 性别）
     *
     * QQ 资料接口（graph.qq.com）使用用户 access_token + openid。
     * 其错误字段为 ret（非登录接口的 errcode），需单独判断。
     *
     * @return array<string, mixed>
     */
    public function userInfo(string $openId, string $accessToken): array
    {
        $config   = $this->app->config();
        $response = $this->app->http()->get('https://graph.qq.com/user/get_user_info', [
            'query' => [
                'access_token'       => $accessToken,
                'oauth_consumer_key' => $config->appId(),
                'openid'             => $openId,
            ],
        ]);

        $api = ApiResponse::fromPsr($response, Platform::Qq);
        $ret = (int) ($api['ret'] ?? 0);
        if ($ret !== 0) {
            throw new ApiException(
                message:   (string) ($api['msg'] ?? '获取用户信息失败'),
                errorCode: $ret,
                platform:  Platform::Qq,
                payload:   $api->toArray(),
                action:    'QQ 获取用户信息',
            );
        }

        return $api->toArray();
    }

    /**
     * 获取 AccessToken（默认命中缓存）
     */
    public function token(bool $forceRefresh = false): string
    {
        $config  = $this->app->config();
        $manager = TokenManager::for($config);

        $token = $forceRefresh
            ? $manager->refresh(Platform::Qq, $this->identity(), self::TOKEN_SCOPE, $this->resolver())
            : $manager->remember(Platform::Qq, $this->identity(), self::TOKEN_SCOPE, $this->resolver());

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
            ->forget(Platform::Qq, $this->identity(), self::TOKEN_SCOPE);
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
            $response = $this->app->http()->get(self::BASE_URL . '/cgi-bin/token', [
                'query' => [
                    'grant_type' => 'client_credential',
                    'appid'      => $config->appId(),
                    'secret'     => $config->secret(),
                ],
            ]);

            $api = ApiResponse::fromPsr($response, Platform::Qq)
                ->throwIfFailed('获取 AccessToken');

            return new TokenResult(
                $api->string('access_token'),
                $api->int('expires_in', TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
    }
}
