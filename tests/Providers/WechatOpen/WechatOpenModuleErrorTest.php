<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatOpen;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\TestCase;

/**
 * 微信开放平台模块错误传播测试
 *
 * 微信接口返回 errcode 时，各方法必须抛出 ApiException（而非静默返回错误体 / 空数组），
 * 避免业务侧把失败当成成功处理。
 */
final class WechatOpenModuleErrorTest extends TestCase
{
    private function app(FakeHttpClient $http): WechatOpenApp
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp',
                'component_secret' => 'sec',
                'token'            => 'tok',
                'encoding_aes_key' => str_repeat('a', 43),
                'token_cache'      => false,
            ],
        ], $http);

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();

        return $app;
    }

    public function testComponentAccessTokenThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('api_component_token', [
            'errcode' => 40013,
            'errmsg'  => 'invalid appid',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->component()->accessToken('TICKET');
    }

    public function testPreAuthCodeThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('api_create_preauthcode', [
            'errcode' => 40001,
            'errmsg'  => 'invalid token',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->component()->preAuthCode('COMP_TOK');
    }

    public function testQueryAuthThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('api_query_auth', [
            'errcode' => 40013,
            'errmsg'  => 'bad code',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->component()->queryAuth('COMP_TOK', 'AUTH_CODE');
    }

    public function testRefreshAuthorizerTokenThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('api_authorizer_token', [
            'errcode' => 40001,
            'errmsg'  => 'x',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->component()->refreshAuthorizerToken('COMP_TOK', 'wxauth', 'REF');
    }

    public function testAuthorizerInfoThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('api_get_authorizer_info', [
            'errcode' => 40013,
            'errmsg'  => 'x',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->component()->authorizerInfo('COMP_TOK', 'wxauth');
    }

    public function testAuthorizerOptionThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('api_get_authorizer_option', [
            'errcode' => 40013,
            'errmsg'  => 'x',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->component()->authorizerOption('COMP_TOK', 'wxauth', 'foo');
    }

    public function testSetAuthorizerOptionThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('api_set_authorizer_option', [
            'errcode' => 40013,
            'errmsg'  => 'x',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->component()->setAuthorizerOption('COMP_TOK', 'wxauth', 'foo', 'bar');
    }

    public function testAuthorizerListThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('api_authorizer_list', [
            'errcode' => 40013,
            'errmsg'  => 'x',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->component()->authorizerList('COMP_TOK');
    }

    public function testMiniProgramSessionThrowsOnError(): void
    {
        $http = (new FakeHttpClient())->stub('jscode2session', [
            'errcode' => 40029,
            'errmsg'  => 'invalid code',
        ]);

        $this->expectException(ApiException::class);
        $this->app($http)->authorizer()->miniProgramSession('wxauth', 'CODE', 'COMP_TOK');
    }

    public function testSuccessResponsesAreNotThrowing(): void
    {
        // 正常响应应返回数据，未被错误传播误伤
        $http = (new FakeHttpClient())->stub('api_get_authorizer_info', [
            'authorizer_info' => ['nick_name' => 'Foo'],
        ]);

        $info = $this->app($http)->component()->authorizerInfo('COMP_TOK', 'wxauth');

        self::assertSame('Foo', $info['authorizer_info']['nick_name'] ?? null);
    }
}
