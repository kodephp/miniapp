<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Tests\TestCase;

/**
 * 令牌缓存管理器 TokenManager 测试
 */
class TokenManagerTest extends TestCase
{
    public function testRememberCachesAndReuses(): void
    {
        $calls = 0;
        $manager = new TokenManager(cache: new ArrayCache(), enabled: true, safetyMargin: 300);

        $resolver = function () use (&$calls): TokenResult {
            $calls++;

            return new TokenResult('TOK_' . $calls, 7200);
        };

        $first = $manager->remember(Platform::Wechat, 'app-1', 'access_token', $resolver);
        $second = $manager->remember(Platform::Wechat, 'app-1', 'access_token', $resolver);

        self::assertSame('TOK_1', $first);
        self::assertSame('TOK_1', $second);
        self::assertSame(1, $calls, 'resolver 不应被重复调用');
        self::assertTrue($manager->has(Platform::Wechat, 'app-1'));
    }

    public function testRefreshForcesReresolve(): void
    {
        $calls = 0;
        $manager = new TokenManager(cache: new ArrayCache(), enabled: true);

        $resolver = function () use (&$calls): TokenResult {
            $calls++;

            return new TokenResult('TOK_' . $calls, 7200);
        };

        $manager->remember(Platform::Wechat, 'app-1', 'access_token', $resolver);
        $refreshed = $manager->refresh(Platform::Wechat, 'app-1', 'access_token', $resolver);

        self::assertSame('TOK_2', $refreshed);
        self::assertSame(2, $calls);
    }

    public function testForgetRemovesCache(): void
    {
        $manager = new TokenManager(cache: new ArrayCache(), enabled: true);
        $resolver = fn (): TokenResult => new TokenResult('TOK', 7200);

        $manager->remember(Platform::Alipay, 'app-2', 'access_token', $resolver);
        self::assertTrue($manager->has(Platform::Alipay, 'app-2'));

        $manager->forget(Platform::Alipay, 'app-2');
        self::assertFalse($manager->has(Platform::Alipay, 'app-2'));
    }

    public function testDisabledBypassesCache(): void
    {
        $calls = 0;
        $manager = new TokenManager(cache: new ArrayCache(), enabled: false);
        $resolver = function () use (&$calls): TokenResult {
            $calls++;

            return new TokenResult('TOK_' . $calls, 7200);
        };

        $manager->remember(Platform::Wechat, 'app-3', 'access_token', $resolver);
        $manager->remember(Platform::Wechat, 'app-3', 'access_token', $resolver);

        self::assertSame(2, $calls, '关闭缓存时每次都应重新换取');
        self::assertFalse($manager->enabled());
    }

    public function testKeyIsHashedAndScoped(): void
    {
        $manager = new TokenManager(cache: new ArrayCache(), enabled: true);

        $key = $manager->key(Platform::Wechat, 'wx-app-secret-identity', 'access_token');
        self::assertStringStartsWith(TokenManager::PREFIX, $key);
        self::assertStringContainsString('_access_token_', $key);
        // identity 应被 md5 哈希，不直接暴露原始密钥
        self::assertStringNotContainsString('wx-app-secret-identity', $key);
        self::assertMatchesRegularExpression('/_[a-f0-9]{32}$/', $key);
    }

    public function testSafetyMarginClamp(): void
    {
        // expiresIn 小于 safetyMargin 时，ttl 不应为负（最低 MIN_TTL）
        $manager = new TokenManager(cache: new ArrayCache(), enabled: true, safetyMargin: 8000);
        $resolver = fn (): TokenResult => new TokenResult('TOK', 7200);

        $manager->remember(Platform::Wechat, 'app-4', 'access_token', $resolver);
        self::assertTrue($manager->has(Platform::Wechat, 'app-4'));
    }
}
