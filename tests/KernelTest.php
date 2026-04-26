<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Exceptions\ConfigException;
use Kode\MiniApp\Kernel;

/**
 * Kernel 门面测试
 */
class KernelTest extends TestCase
{
    public function testWechatProvider(): void
    {
        $kernel = new Kernel([
            'wechat' => ['app_id' => 'wx123', 'secret' => 'secret'],
        ]);

        $provider = $kernel->wechat();

        self::assertSame(Platform::Wechat, $provider->name());
        self::assertSame('wx123', $provider->config()->appId());
    }

    public function testAlipayProvider(): void
    {
        $kernel = new Kernel([
            'alipay' => ['app_id' => '2024', 'private_key' => 'key'],
        ]);

        $provider = $kernel->alipay();

        self::assertSame(Platform::Alipay, $provider->name());
        self::assertSame('2024', $provider->config()->appId());
    }

    public function testBaiduProvider(): void
    {
        $kernel = new Kernel([
            'baidu' => ['app_id' => 'baidu123', 'secret' => 'secret'],
        ]);

        $provider = $kernel->baidu();

        self::assertSame(Platform::Baidu, $provider->name());
        self::assertSame('baidu123', $provider->config()->appId());
    }

    public function testQqProvider(): void
    {
        $kernel = new Kernel([
            'qq' => ['app_id' => 'qq123', 'secret' => 'secret'],
        ]);

        $provider = $kernel->qq();

        self::assertSame(Platform::Qq, $provider->name());
        self::assertSame('qq123', $provider->config()->appId());
    }

    public function testMissingConfigThrowsException(): void
    {
        $this->expectException(ConfigException::class);

        $kernel = new Kernel([]);
        $kernel->wechat();
    }

    public function testAppShortcut(): void
    {
        $kernel = new Kernel([
            'wechat' => ['app_id' => 'wx123', 'secret' => 'secret'],
        ]);

        $app = $kernel->app(Platform::Wechat, 'default');

        self::assertSame('default', $app->name());
    }
}
