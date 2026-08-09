<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;

/**
 * SessionKeyManager 单元测试
 *
 * 覆盖 store / get / forget / has / 关闭托管 / TTL 过期等核心行为。
 */
class SessionKeyManagerTest extends TestCase
{
    public function testStoreAndGet(): void
    {
        $manager = new SessionKeyManager(cache: new ArrayCache(), enabled: true);

        self::assertFalse($manager->has('o1'));
        $manager->store('o1', 'sk-123');
        self::assertTrue($manager->has('o1'));
        self::assertSame('sk-123', $manager->get('o1'));
    }

    public function testForget(): void
    {
        $manager = new SessionKeyManager(cache: new ArrayCache(), enabled: true);
        $manager->store('o1', 'sk-123');

        $manager->forget('o1');
        self::assertNull($manager->get('o1'));
    }

    public function testDisabledManagerIsNoop(): void
    {
        $manager = new SessionKeyManager(cache: new ArrayCache(), enabled: false);

        $manager->store('o1', 'sk-123');
        self::assertNull($manager->get('o1'), '关闭托管后 store 应为空操作');
        self::assertFalse($manager->enabled());
    }

    public function testEmptyOpenIdOrKeyIsIgnored(): void
    {
        $manager = new SessionKeyManager(cache: new ArrayCache(), enabled: true);

        $manager->store('', 'sk-123');
        $manager->store('o1', '');
        self::assertNull($manager->get(''));
        self::assertNull($manager->get('o1'));
    }

    public function testTtlExpiry(): void
    {
        $cache = new ArrayCache();
        $manager = new SessionKeyManager(cache: $cache, enabled: true, ttl: 1);

        $manager->store('o1', 'sk-123');
        self::assertSame('sk-123', $manager->get('o1'));

        // 跨过 TTL 后失效
        sleep(2);
        self::assertNull($manager->get('o1'), '超过 TTL 后应取回 null');
    }

    public function testPerCallTtlOverridesDefault(): void
    {
        $manager = new SessionKeyManager(cache: new ArrayCache(), enabled: true, ttl: 60);

        $manager->store('o1', 'sk-123', 1);
        self::assertSame('sk-123', $manager->get('o1'));
        sleep(2);
        self::assertNull($manager->get('o1'), '单次调用传入的 TTL 应优先生效');
    }

    public function testKeyIsHashed(): void
    {
        $manager = new SessionKeyManager(cache: new ArrayCache(), enabled: true);

        // 明文 openid 不应直接出现在缓存键里（避免 key 外泄）
        self::assertStringStartsWith(SessionKeyManager::PREFIX, $manager->key('o1'));
        self::assertStringNotContainsString('o1', $manager->key('o1'));
    }

    public function testForResolvesCacheFromConfig(): void
    {
        $cache = new ArrayCache();
        $kernel = new Kernel([
            'wechat' => [
                'app_id'          => 'wx123',
                'app_secret'      => 's3cr3t',
                'cache'           => $cache,
                'session_key_ttl' => 120,
            ],
        ]);

        /** @var \Kode\MiniApp\Providers\Wechat\WechatApp $app */
        $app = $kernel->wechat()->app();

        $manager = SessionKeyManager::for($app->config());
        $manager->store('o1', 'sk-xyz');
        self::assertSame('sk-xyz', $manager->get('o1'));
        self::assertTrue($cache->has($manager->key('o1')));
    }

    public function testForDisabledViaConfig(): void
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'          => 'wx123',
                'app_secret'      => 's3cr3t',
                'session_key_cache' => false,
            ],
        ]);

        /** @var \Kode\MiniApp\Providers\Wechat\WechatApp $app */
        $app = $kernel->wechat()->app();

        $manager = SessionKeyManager::for($app->config());
        self::assertFalse($manager->enabled(), '配置 session_key_cache=false 应禁用托管');
    }
}
