<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core\Fixtures;

use Psr\SimpleCache\CacheInterface;

/**
 * 状态化 PSR-16 假缓存：用于确定性复现「缓存击穿保护（单飞）」场景。
 *
 * 设定：另一个进程（holder）已抢到锁并正在刷新；本进程（waiter）在锁持有期间
 * 不得自行刷新，而应复用 holder 最终写入的共享令牌。
 *
 * - tokenKey：前 $tokenNullGets 次 get 返回 null（holder 尚未写完），之后返回共享令牌；
 *   设为极大值即模拟「令牌始终不出现」（可用性兜底路径）。
 * - lockKey：前 $lockHeldGets 次 get 返回 holder 的 owner（锁持续持有），之后返回 null
 *   （holder 释放锁）；设为极大值即模拟「锁始终被持有」。
 *
 * 注：本夹具不真正存储（set 为 no-op），由 waiter 测试断言「resolver 不被调用」，
 * 由 winner 测试改用真实 ArrayCache。
 */
final class SingleFlightFakeCache implements CacheInterface
{
    private int $tokenGets = 0;

    private int $lockGets = 0;

    /**
     * @param int $tokenNullGets tokenKey 前 N 次 get 返回 null（holder 尚未写完）
     * @param int $lockHeldGets lockKey 前 N 次 get 返回 owner（锁持续持有）
     */
    public function __construct(
        private readonly string $sharedToken,
        private readonly string $lockOwner,
        private readonly int $tokenNullGets = 2,
        private readonly int $lockHeldGets = 50,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (str_ends_with($key, '_lock')) {
            $this->lockGets++;

            return $this->lockGets <= $this->lockHeldGets ? $this->lockOwner : null;
        }

        $this->tokenGets++;

        return $this->tokenGets <= $this->tokenNullGets ? null : $this->sharedToken;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    /** @param iterable<string> $keys */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->get($k, $default);
        }

        return $out;
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    /** @param iterable<string> $keys */
    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
