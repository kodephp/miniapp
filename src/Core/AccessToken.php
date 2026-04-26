<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Kode\MiniApp\Contracts\Platform;
use Psr\SimpleCache\CacheInterface;

/**
 * AccessToken 缓存管理
 */
final class AccessToken
{
    private string $prefix = 'kode_access_token_';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * 获取缓存的 token
     */
    public function get(Platform $platform, string $appId): ?string
    {
        $key = $this->key($platform, $appId);
        $value = $this->cache->get($key);

        return is_string($value) ? $value : null;
    }

    /**
     * 设置 token，自动计算过期时间（预留 60 秒缓冲）
     */
    public function set(Platform $platform, string $appId, string $token, int $expiresIn = 7200): void
    {
        $key = $this->key($platform, $appId);
        $this->cache->set($key, $token, max(0, $expiresIn - 60));
    }

    /**
     * 删除 token
     */
    public function forget(Platform $platform, string $appId): void
    {
        $this->cache->delete($this->key($platform, $appId));
    }

    private function key(Platform $platform, string $appId): string
    {
        return $this->prefix . $platform->value . '_' . $appId;
    }
}
