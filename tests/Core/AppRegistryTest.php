<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\AppRegistry;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;

/**
 * 应用注册表测试（按 appid 路由多 Kernel）
 */
final class AppRegistryTest extends TestCase
{
    private function kernel(string $appId): Kernel
    {
        return new Kernel([
            'wechat' => [
                'app_id'     => $appId,
                'app_secret' => 'secret',
            ],
        ]);
    }

    public function testRegisterAndResolveByAppId(): void
    {
        $a = $this->kernel('wxa111');
        $b = $this->kernel('wxb222');

        $registry = (new AppRegistry())
            ->register('wxa111', $a)
            ->register('wxb222', $b);

        self::assertTrue($registry->has('wxa111'));
        self::assertFalse($registry->has('wxc333'));
        self::assertSame($a, $registry->kernel('wxa111'));

        self::assertSame('wxa111', $registry->app('wxa111', Platform::Wechat)->config()->appId());
        self::assertSame('wxb222', $registry->app('wxb222', Platform::Wechat)->config()->appId());
    }

    public function testResolveUnknownThrows(): void
    {
        $registry = new AppRegistry();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未找到 appid');

        $registry->kernel('unknown');
    }
}
