<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatWork;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;
use Kode\MiniApp\Providers\WechatWork\WechatWorkConfig;
use Kode\MiniApp\Providers\WechatWork\WechatWorkProvider;
use Kode\MiniApp\Tests\TestCase;

/**
 * 微信企业号 Provider 测试
 */
class WechatWorkProviderTest extends TestCase
{
    public function testProvider(): void
    {
        $kernel = new Kernel([
            'wechat_work' => [
                'corp_id'  => 'corp123',
                'secret'   => 'secret',
                'agent_id' => '1000002',
            ],
        ]);

        $provider = $kernel->wechatWork();
        $config   = $provider->config();

        self::assertInstanceOf(WechatWorkProvider::class, $provider);
        self::assertInstanceOf(WechatWorkConfig::class, $config);
        self::assertSame(Platform::WechatWork, $provider->name());
        self::assertSame('corp123', $config->corpId());
        self::assertSame('1000002', $config->agentId());
    }

    public function testAppModules(): void
    {
        $kernel = new Kernel([
            'wechat_work' => [
                'corp_id'  => 'corp123',
                'secret'   => 'secret',
                'agent_id' => '1000002',
            ],
        ]);

        $app = $kernel->wechatWork()->app();

        self::assertInstanceOf(WechatWorkApp::class, $app);
        self::assertSame('default', $app->name());
        self::assertInstanceOf(\Kode\MiniApp\Providers\WechatWork\Modules\Auth::class, $app->auth());
        self::assertInstanceOf(\Kode\MiniApp\Providers\WechatWork\Modules\Contact::class, $app->contact());
        self::assertInstanceOf(\Kode\MiniApp\Providers\WechatWork\Modules\Message::class, $app->message());
        self::assertInstanceOf(\Kode\MiniApp\Providers\WechatWork\Modules\Approval::class, $app->approval());
    }
}
