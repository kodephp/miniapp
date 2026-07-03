<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatOpen;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenConfig;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenProvider;
use Kode\MiniApp\Providers\WechatOpen\Modules\Authorizer;
use Kode\MiniApp\Providers\WechatOpen\Modules\Component;
use Kode\MiniApp\Providers\WechatOpen\Modules\Crypto;
use Kode\MiniApp\Providers\WechatOpen\Modules\OpenApp;
use Kode\MiniApp\Providers\WechatOpen\Modules\UnionId;
use Kode\MiniApp\Tests\TestCase;

/**
 * 微信开放平台 Provider 测试
 */
class WechatOpenProviderTest extends TestCase
{
    public function testProvider(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        $provider = $kernel->wechatOpen();
        $config   = $provider->config();

        self::assertInstanceOf(WechatOpenProvider::class, $provider);
        self::assertInstanceOf(WechatOpenConfig::class, $config);
        self::assertSame(Platform::WechatOpen, $provider->name());
        self::assertSame('wxcomp123', $config->componentAppId());
        self::assertSame('comp-secret', $config->componentSecret());
        self::assertSame('verify-token', $config->token());
    }

    public function testAppModules(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        $app = $kernel->wechatOpen()->app();

        self::assertInstanceOf(WechatOpenApp::class, $app);
        self::assertSame('default', $app->name());
        self::assertInstanceOf(Component::class, $app->component());
        self::assertInstanceOf(Authorizer::class, $app->authorizer());
        self::assertInstanceOf(OpenApp::class, $app->openApp());
        self::assertInstanceOf(Crypto::class, $app->crypto());
        self::assertInstanceOf(UnionId::class, $app->unionId());
    }

    public function testWechatEcosystemCheck(): void
    {
        self::assertTrue(Platform::Wechat->isWechatEcosystem());
        self::assertTrue(Platform::WechatOpen->isWechatEcosystem());
        self::assertTrue(Platform::WechatWork->isWechatEcosystem());
        self::assertTrue(Platform::Qq->isWechatEcosystem());
        self::assertFalse(Platform::Alipay->isWechatEcosystem());
        self::assertFalse(Platform::Dingtalk->isWechatEcosystem());
    }

    public function testPlatformLabel(): void
    {
        self::assertSame('微信', Platform::Wechat->label());
        self::assertSame('微信开放平台', Platform::WechatOpen->label());
        self::assertSame('微信企业号', Platform::WechatWork->label());
    }

    public function testConfigHelpers(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
                'pre_auth_apps'    => ['wxapp1', 'wxapp2'],
            ],
        ]);

        /** @var WechatOpenConfig $config */
        $config = $kernel->wechatOpen()->config();

        self::assertSame('wxcomp123', $config->componentAppId());
        self::assertSame('comp-secret', $config->componentSecret());
        self::assertSame('verify-token', $config->token());
        self::assertSame(str_repeat('a', 43), $config->aesKey());
        self::assertSame(['wxapp1', 'wxapp2'], $config->preAuthorizeApps());
    }

    public function testWechatOpenBridgesToWechat(): void
    {
        $kernel = new Kernel([
            'wechat'      => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $openApp */
        $openApp = $kernel->wechatOpen()->app();
        $wechat  = $openApp->wechat();

        self::assertNotNull($wechat);
        self::assertSame('wxapp0000000000', $wechat->config()->appId());
    }

    public function testWechatBridgesToWechatOpen(): void
    {
        $kernel = new Kernel([
            'wechat'      => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var \Kode\MiniApp\Providers\Wechat\WechatApp $wechatApp */
        $wechatApp    = $kernel->wechat()->app();
        $openProvider = $wechatApp->wechatOpen();

        self::assertNotNull($openProvider);
        self::assertSame('wxcomp123', $openProvider->config()->componentAppId());
    }
}
