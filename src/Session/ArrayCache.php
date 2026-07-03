<?php

declare(strict_types=1);

namespace Kode\MiniApp\Session;

use Psr\SimpleCache\CacheInterface;

/**
 * 简单的内存 PSR-16 Cache 实现（用于测试 / 单进程场景）
 *
 * 生产环境请使用 Redis、Memcached 等支持共享存储的实现。
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }
        $entry = $this->data[$key];
        if (!is_array($entry) || !array_key_exists('expires', $entry)) {
            return $default;
        }
        $expires = $entry['expires'];
        if ($expires !== null && $expires < time()) {
            unset($this->data[$key]);
            return $default;
        }
        return $entry['value'] ?? $default;
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
     * @param iterable<string, mixed> $keys
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
     * @param iterable<string, mixed> $keys
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
        return $this->get($key, null) !== null || array_key_exists($key, $this->data);
    }

    private function normalizeTtl(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
        if ($ttl instanceof \DateInterval) {
            return time() + (new \DateTime())->add($ttl)->getTimestamp() - time();
        }
        return time() + $ttl;
    }
}
