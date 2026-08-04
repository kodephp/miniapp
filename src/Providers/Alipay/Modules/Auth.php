<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Providers\Alipay\AlipayGateway;

/**
 * 支付宝登录/授权模块
 */
readonly class Auth
{
    private const string METHOD_TOKEN = 'alipay.system.oauth.token';
    private const string METHOD_USER  = 'alipay.user.info.share';

    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 通过 auth_code 换取 AccessToken
     *
     * 注意：grant_type / code 属于顶层请求参数，不能放进 biz_content。
     *
     * @return array<string, mixed>
     */
    public function token(string $code): array
    {
        return $this->app->gateway()
            ->execute(self::METHOD_TOKEN, [], [
                'grant_type' => 'authorization_code',
                'code'       => $code,
            ])
            ->throwIfFailed('支付宝换取 AccessToken')
            ->array(AlipayGateway::responseNode(self::METHOD_TOKEN));
    }

    /**
     * 刷新 AccessToken
     *
     * @return array<string, mixed>
     */
    public function refresh(string $refreshToken): array
    {
        return $this->app->gateway()
            ->execute(self::METHOD_TOKEN, [], [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ])
            ->throwIfFailed('支付宝刷新 AccessToken')
            ->array(AlipayGateway::responseNode(self::METHOD_TOKEN));
    }

    /**
     * 获取用户信息
     *
     * @return array<string, mixed>
     */
    public function user(string $accessToken): array
    {
        return $this->app->gateway()
            ->execute(self::METHOD_USER, [], ['auth_token' => $accessToken])
            ->throwIfFailed('支付宝获取用户信息')
            ->array(AlipayGateway::responseNode(self::METHOD_USER));
    }
}
