<?php

declare(strict_types=1);

namespace Kode\MiniApp\Session;

use Psr\SimpleCache\CacheInterface;

/**
 * 基于 PSR-16 Cache 的会话存储实现
 *
 * 使用两个 key 空间：
 *   1. 会话主数据：kode_session:{sessionId}
 *   2. 索引集合：  kode_session_idx:{indexKey} -> Set<sessionId>
 *
 * 索引 key 的命名：
 *   - u:{unionId}                  - 该 unionId 的所有会话
 *   - c:{client}:{clientId}        - 该客户端的所有会话
 *   - s:{unionId}:{scene}          - 该 unionId + 端口的所有会话
 *
 * 业务侧可注入自己的 Cache（Redis / Memcached / 文件 等），
 * 只要实现 PSR-16 CacheInterface 即可。
 */
final class CacheSessionStorage implements SessionStorageInterface
{
    private const SESSION_PREFIX = 'kode_session:';
    private const INDEX_PREFIX   = 'kode_session_idx:';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function write(string $sessionId, array $data, int $ttl): void
    {
        $this->cache->set(self::SESSION_PREFIX . $sessionId, $data, $ttl);

        // 同时维护索引
        $indexKeys = $this->buildIndexKeys($data);
        foreach ($indexKeys as $indexKey) {
            $fullKey = self::INDEX_PREFIX . $indexKey;
            $set = $this->cache->get($fullKey, []);
            if (!is_array($set)) {
                $set = [];
            }
            $set[$sessionId] = time();
            $this->cache->set($fullKey, $set, $ttl);
        }
    }

    public function read(string $sessionId): ?array
    {
        $data = $this->cache->get(self::SESSION_PREFIX . $sessionId);
        return is_array($data) ? $data : null;
    }

    public function delete(string $sessionId): void
    {
        $data = $this->read($sessionId);
        if ($data !== null) {
            $this->cache->delete(self::SESSION_PREFIX . $sessionId);
            $indexKeys = $this->buildIndexKeys($data);
            foreach ($indexKeys as $indexKey) {
                $fullKey = self::INDEX_PREFIX . $indexKey;
                $set = $this->cache->get($fullKey, []);
                if (!is_array($set)) {
                    continue;
                }
                unset($set[$sessionId]);
                if ($set === []) {
                    $this->cache->delete($fullKey);
                } else {
                    $this->cache->set($fullKey, $set, 86400 * 365);
                }
            }
        }
    }

    public function findByIndex(string $indexKey): array
    {
        $set = $this->cache->get(self::INDEX_PREFIX . $indexKey, []);
        if (!is_array($set)) {
            return [];
        }
        return array_keys($set);
    }

    public function countByIndex(string $indexKey): int
    {
        $set = $this->cache->get(self::INDEX_PREFIX . $indexKey, []);
        return is_array($set) ? count($set) : 0;
    }

    public function cleanExpired(): int
    {
        // PSR-16 的 Cache 通常自带 TTL 过期机制，
        // 此方法作为可选扩展点，业务侧可自行实现批量清理逻辑。
        return 0;
    }

    /**
     * 构造索引 key 列表
     *
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function buildIndexKeys(array $data): array
    {
        $unionId  = (string) ($data['unionId']  ?? '');
        $client   = (string) ($data['client']   ?? '');
        $clientId = (string) ($data['clientId'] ?? '');
        $scene    = (string) ($data['scene']    ?? '');

        $keys = [];
        if ($unionId !== '') {
            $keys[] = "u:{$unionId}";
        }
        if ($client !== '' && $clientId !== '') {
            $keys[] = "c:{$client}:{$clientId}";
        }
        if ($unionId !== '' && $scene !== '') {
            $keys[] = "s:{$unionId}:{$scene}";
        }
        return $keys;
    }
}
