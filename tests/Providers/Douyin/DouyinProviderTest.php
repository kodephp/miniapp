<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Douyin;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Douyin\DouyinApp;
use Kode\MiniApp\Providers\Douyin\DouyinConfig;
use Kode\MiniApp\Providers\Douyin\DouyinProvider;
use Kode\MiniApp\Tests\TestCase;

/**
 * 抖音 Provider 测试
 */
class DouyinProviderTest extends TestCase
{
    public function testProvider(): void
    {
        $kernel = new Kernel([
            'douyin' => [
                'app_id' => 'tt123',
                'secret' => 'secret',
                'salt'   => 'salt123',
                'mch_id' => 'mch001',
            ],
        ]);

        $provider = $kernel->douyin();
        $config   = $provider->config();

        self::assertInstanceOf(DouyinProvider::class, $provider);
        self::assertInstanceOf(DouyinConfig::class, $config);
        self::assertSame(Platform::Douyin, $provider->name());
        self::assertSame('tt123', $config->appId());
        self::assertSame('secret', $config->secret());
        self::assertSame('salt123', $config->salt());
        self::assertSame('mch001', $config->mchId());
    }

    public function testAppModules(): void
    {
        $kernel = new Kernel([
            'douyin' => [
                'app_id' => 'tt123',
                'secret' => 'secret',
                'salt'   => 'salt123',
            ],
        ]);

        $app = $kernel->douyin()->app();

        self::assertInstanceOf(DouyinApp::class, $app);
        self::assertSame('default', $app->name());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Douyin\Modules\Auth::class, $app->auth());
        self::assertInstanceOf(\Kode\MiniApp\Providers\Douyin\Modules\Video::class, $app->video());
    }
}
