<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Kode\MiniApp\Contracts\Platform;
use Psr\SimpleCache\CacheInterface;

/**
 * AccessToken 缓存管理（轻量门面）
 *
 * 简单读写场景直接使用本类；需要防击穿、提前刷新等生产级能力时
 * 使用 remember() 或直接使用 {@see TokenManager}。
 */
final class AccessToken
{
    /**
     * 缓存键前缀
     */
    public const string PREFIX = 'kode_access_token_';

    /**
     * 写入时预留的过期缓冲（秒）
     */
    public const int SAFETY_MARGIN = 60;

    private readonly TokenManager $manager;

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
        $this->manager = new TokenManager(
            cache: $this->cache,
            safetyMargin: self::SAFETY_MARGIN,
        );
    }

    /**
     * 获取缓存的 token
     */
    public function get(Platform $platform, string $appId): ?string
    {
        $value = $this->cache->get($this->key($platform, $appId));

        return is_string($value) ? $value : null;
    }

    /**
     * 设置 token，自动计算过期时间（预留 60 秒缓冲）
     */
    public function set(Platform $platform, string $appId, string $token, int $expiresIn = 7200): void
    {
        $this->cache->set(
            $this->key($platform, $appId),
            $token,
            max(0, $expiresIn - self::SAFETY_MARGIN)
        );
    }

    /**
     * 删除 token
     */
    public function forget(Platform $platform, string $appId): void
    {
        $this->cache->delete($this->key($platform, $appId));
        $this->manager->forget($platform, $appId);
    }

    /**
     * 读取缓存令牌，缺失时刷新（带缓存击穿保护）
     *
     * @param callable(): TokenResult $resolver
     */
    public function remember(
        Platform $platform,
        string $appId,
        callable $resolver,
        string $scope = 'access_token',
    ): mixed {
        return $this->manager->remember($platform, $appId, $scope, $resolver);
    }

    /**
     * 获取底层 TokenManager
     */
    public function manager(): TokenManager
    {
        return $this->manager;
    }

    private function key(Platform $platform, string $appId): string
    {
        return self::PREFIX . $platform->value . '_' . $appId;
    }
}
