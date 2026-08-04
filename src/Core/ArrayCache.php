<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Psr\SimpleCache\CacheInterface;

/**
 * 内存 PSR-16 缓存实现（用于测试 / 单进程 CLI 场景）
 *
 * 生产环境请使用 Redis、Memcached 等支持跨进程共享的实现，
 * 否则 AccessToken 缓存与多端登录约束无法在多 worker 间生效。
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: int|null}> */
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }

        $entry   = $this->data[$key];
        $expires = $entry['expires'];

        if ($expires !== null && $expires <= time()) {
            unset($this->data[$key]);

            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->data[$key] = [
            'value'   => $value,
            'expires' => $this->normalizeTtl($ttl),
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->data = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[(string) $key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $miss = new \stdClass();

        return $this->get($key, $miss) !== $miss;
    }

    /**
     * 当前有效键数量（测试辅助）
     */
    public function count(): int
    {
        $count = 0;
        foreach (array_keys($this->data) as $key) {
            if ($this->has($key)) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeTtl(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        return time() + $ttl;
    }
}
