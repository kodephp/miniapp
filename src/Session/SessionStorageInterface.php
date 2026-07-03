<?php

declare(strict_types=1);

namespace Kode\MiniApp\Session;

/**
 * 会话存储接口
 *
 * 定义 Session 持久化所需的最基本操作。
 * 默认提供基于 PSR-16 Cache 的实现（CacheSessionStorage），
 * 业务侧可基于 Redis、MySQL、文件等实现自己的存储。
 */
interface SessionStorageInterface
{
    /**
     * 写入会话
     *
     * @param array<string, mixed> $data
     */
    public function write(string $sessionId, array $data, int $ttl): void;

    /**
     * 读取会话
     *
     * @return array<string, mixed>|null
     */
    public function read(string $sessionId): ?array;

    /**
     * 删除单个会话
     */
    public function delete(string $sessionId): void;

    /**
     * 通过索引键查找会话 ID 列表
     *
     * @return array<int, string>
     */
    public function findByIndex(string $indexKey): array;

    /**
     * 通过索引键统计会话数量
     */
    public function countByIndex(string $indexKey): int;

    /**
     * 清理过期会话（可选实现）
     */
    public function cleanExpired(): int;
}
