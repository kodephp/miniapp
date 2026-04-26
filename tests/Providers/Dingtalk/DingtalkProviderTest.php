<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Dingtalk;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;

/**
 * 钉钉 Provider 测试
 */
class DingtalkProviderTest extends TestCase
{
    public function testProvider(): void
    {
        $kernel = new Kernel([
            'dingtalk' => [
                'app_id'     => 'ding123',
                'app_key'    => 'key123',
                'app_secret' => 'secret',
                'agent_id'   => '123456',
            ],
        ]);

        $provider = $kernel->dingtalk();

        self::assertSame(Platform::Dingtalk, $provider->name());
        self::assertSame('key123', $provider->config()->appKey());
        self::assertSame('secret', $provider->config()->appSecret());
    }

    public function testAppModules(): void
    {
        $kernel = new Kernel([
            'dingtalk' => [
                'app_id'     => 'ding123',
                'app_key'    => 'key123',
                'app_secret' => 'secret',
                'agent_id'   => '123456',
            ],
        ]);

        $app = $kernel->dingtalk()->app();

        self::assertSame('default', $app->name());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Dingtalk\Modules\Auth::class, $app->auth());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Dingtalk\Modules\Contact::class, $app->contact());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Dingtalk\Modules\Message::class, $app->message());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Dingtalk\Modules\Approval::class, $app->approval());
    }
}
