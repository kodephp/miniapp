<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Lark;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;

/**
 * 飞书 Provider 测试
 */
class LarkProviderTest extends TestCase
{
    public function testProvider(): void
    {
        $kernel = new Kernel([
            'lark' => [
                'app_id'     => 'cli_123',
                'secret'     => 'secret',
                'is_feishu'  => true,
            ],
        ]);

        $provider = $kernel->lark();

        self::assertSame(Platform::Lark, $provider->name());
        self::assertSame('cli_123', $provider->config()->appId());
        self::assertTrue($provider->config()->isFeishu());
        self::assertSame('https://open.feishu.cn', $provider->config()->baseUrl());
    }

    public function testLarkOversea(): void
    {
        $kernel = new Kernel([
            'lark' => [
                'app_id'     => 'cli_456',
                'secret'     => 'secret',
                'is_feishu'  => false,
            ],
        ]);

        $provider = $kernel->lark();

        self::assertFalse($provider->config()->isFeishu());
        self::assertSame('https://open.larksuite.com', $provider->config()->baseUrl());
    }

    public function testAppModules(): void
    {
        $kernel = new Kernel([
            'lark' => [
                'app_id'     => 'cli_123',
                'secret'     => 'secret',
                'is_feishu'  => true,
            ],
        ]);

        $app = $kernel->lark()->app();

        self::assertSame('default', $app->name());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Lark\Modules\Auth::class, $app->auth());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Lark\Modules\Contact::class, $app->contact());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Lark\Modules\Message::class, $app->message());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Lark\Modules\Approval::class, $app->approval());
    }
}
