<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Tests\Core\Fixtures\SingleFlightFakeCache;
use Kode\MiniApp\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * TokenManager 缓存击穿保护（single-flight）确定性 e2e。
 *
 * 无需真线程：用状态化 FakeCache 复现「另一进程已持锁刷新，本进程作为 waiter
 * 应复用共享令牌而非自行刷新」的生产关键路径。
 */
#[CoversClass(TokenManager::class)]
final class TokenManagerSingleFlightTest extends TestCase
{
    /**
     * 获胜方（无竞争）：resolver 仅调用一次，令牌写入缓存并复用。
     */
    public function testWinnerRefreshesExactlyOnceAndCaches(): void
    {
        $calls   = 0;
        $manager = new TokenManager(cache: new ArrayCache(), enabled: true, safetyMargin: 300);
        $resolver = function () use (&$calls): TokenResult {
            $calls++;

            return new TokenResult('token-' . $calls, 7200);
        };

        $first  = $manager->remember(Platform::Wechat, 'app-1', 'access_token', $resolver);
        $second = $manager->remember(Platform::Wechat, 'app-1', 'access_token', $resolver);

        self::assertSame('token-1', $first);
        self::assertSame('token-1', $second, '第二次应命中缓存，不应再次刷新');
        self::assertSame(1, $calls, '单飞获胜方只应刷新一次');
        self::assertTrue($manager->has(Platform::Wechat, 'app-1'));
    }

    /**
     * 等待方（锁被持有 + 共享令牌稍后出现）：不得自行刷新，
     * 必须复用 holder 写入的共享令牌（缓存击穿保护核心保证）。
     */
    public function testWaiterReusesSharedTokenInsteadOfRefreshing(): void
    {
        $cache   = new SingleFlightFakeCache(sharedToken: 'shared_by_holder', lockOwner: 'holder-owner');
        $manager = new TokenManager(cache: $cache, enabled: true, safetyMargin: 300);

        // 若 waiter 误触发刷新，resolver 会抛异常使测试失败 —— 证明单飞生效。
        $resolver = static function (): TokenResult {
            throw new \RuntimeException('waiter 不应自行刷新（单飞击穿保护失效）');
        };

        $result = $manager->remember(Platform::Wechat, 'app-x', 'access_token', $resolver);

        self::assertSame('shared_by_holder', $result, '等待方应复用 holder 写入的共享令牌');
    }

    /**
     * 兜底路径（锁卡死 + 令牌始终不出现）：等待超时后可用性优先，自行刷新返回，无死锁。
     */
    public function testWaiterFallsBackToRefreshWhenLockStuck(): void
    {
        // tokenNullGets/lockHeldGets 设极大值：令牌永不出现、锁始终被持有。
        $cache   = new SingleFlightFakeCache(
            sharedToken: 'never',
            lockOwner: 'holder',
            tokenNullGets: 9999,
            lockHeldGets: 9999,
        );
        // 缩短等待窗口以加速测试（5 次 × 1ms）。
        $manager = new TokenManager(
            cache: $cache,
            enabled: true,
            safetyMargin: 300,
            waitAttempts: 5,
            waitIntervalUs: 1000,
        );

        $calls    = 0;
        $resolver = function () use (&$calls): TokenResult {
            $calls++;

            return new TokenResult('fallback-' . $calls, 7200);
        };

        $result = $manager->remember(Platform::Wechat, 'app-y', 'access_token', $resolver);

        self::assertSame('fallback-1', $result, '锁卡死时应可用性优先自行刷新');
        self::assertSame(1, $calls);
    }
}
