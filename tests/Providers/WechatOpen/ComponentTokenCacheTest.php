<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatOpen;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\TestCase;

/**
 * 开放平台 token 缓存测试（component_access_token / authorizer_access_token）
 */
final class ComponentTokenCacheTest extends TestCase
{
    private function app(FakeHttpClient $http, ArrayCache $cache): WechatOpenApp
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
                'cache'            => $cache,
            ],
        ], $http);

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();

        return $app;
    }

    public function testComponentAccessTokenIsCached(): void
    {
        $cache = new ArrayCache();
        $http  = (new FakeHttpClient())->stub('api_component_token', [
            'component_access_token' => 'COMP_TOK_001',
            'expires_in'             => 7200,
        ]);

        $app = $this->app($http, $cache);

        // 第二次即便传入不同的 ticket，也应命中缓存（token 与 ticket 无关，2h 内有效）
        $first  = $app->component()->accessToken('TICKET_001');
        $second = $app->component()->accessToken('TICKET_002');

        self::assertSame('COMP_TOK_001', $first['component_access_token']);
        self::assertSame('COMP_TOK_001', $second['component_access_token']);
        self::assertSame(1, $http->postJsonCalls(), '第二次应命中缓存，不再请求微信');
    }

    public function testAuthorizerAccessTokenIsCachedAndRefreshable(): void
    {
        $cache = new ArrayCache();
        $http  = (new FakeHttpClient())->stub('api_authorizer_token', [
            'authorizer_access_token'  => 'AUTH_TOK_001',
            'authorizer_refresh_token' => 'REFRESH_001',
            'expires_in'               => 7200,
        ]);

        $app = $this->app($http, $cache);

        $token1 = $app->component()->authorizerAccessToken(
            componentAccessToken:    'COMP_TOK',
            authorizerAppId:         'wxauth123',
            authorizerRefreshToken:  'REFRESH_001',
        );
        $token2 = $app->component()->authorizerAccessToken(
            componentAccessToken:    'COMP_TOK',
            authorizerAppId:         'wxauth123',
            authorizerRefreshToken:  'REFRESH_001',
        );

        self::assertSame('AUTH_TOK_001', $token1);
        self::assertSame('AUTH_TOK_001', $token2);
        self::assertSame(1, $http->postJsonCalls(), '第二次应命中缓存');

        // 强制刷新应再次请求微信
        $token3 = $app->component()->authorizerAccessToken(
            componentAccessToken:    'COMP_TOK',
            authorizerAppId:         'wxauth123',
            authorizerRefreshToken:  'REFRESH_001',
            forceRefresh:            true,
        );

        self::assertSame('AUTH_TOK_001', $token3);
        self::assertSame(2, $http->postJsonCalls(), '强制刷新应再次请求微信');
    }
}
