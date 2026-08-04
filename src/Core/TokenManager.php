<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\Platform;
use Psr\SimpleCache\CacheInterface;

/**
 * AccessToken 缓存管理器（带缓存击穿保护）
 *
 * 解决三个生产问题：
 * 1. 每次调用接口都重新换取 access_token —— 微信/企业微信等平台有每日调用配额，
 *    高频换取会直接打满配额并拖慢接口；
 * 2. 令牌临期抖动 —— 缓存 TTL 比平台有效期提前 safetyMargin 秒过期，避免
 *    "刚取到就失效"；
 * 3. 并发击穿 —— 缓存失效瞬间多个进程同时刷新，会互相顶掉令牌。这里用
 *    PSR-16 实现单飞锁（single-flight），只放一个进程去刷新，其余进程等待复用。
 *
 * 用法：
 *   $token = TokenManager::for($config)->remember(
 *       Platform::Wechat,
 *       $config->appId(),
 *       'access_token',
 *       fn () => new TokenResult($accessToken, 7200),
 *   );
 *
 * 配置项（写在平台配置数组中）：
 *   'cache'                => PSR-16 实例，默认走 Cache::getInstance()
 *   'token_cache'          => false 可关闭缓存（调试用）
 *   'token_safety_margin'  => 提前过期秒数，默认 300
 */
final class TokenManager
{
    /**
     * 缓存键前缀
     */
    public const string PREFIX = 'kode_miniapp_token_';

    /**
     * 锁键后缀
     */
    public const string LOCK_SUFFIX = '_lock';

    /**
     * 默认提前过期缓冲（秒）
     */
    public const int DEFAULT_SAFETY_MARGIN = 300;

    /**
     * 最短缓存时长（秒），避免 safetyMargin 大于有效期时缓存被直接跳过
     */
    public const int MIN_TTL = 60;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly bool $enabled = true,
        private readonly int $safetyMargin = self::DEFAULT_SAFETY_MARGIN,
        private readonly int $lockTtl = 10,
        private readonly int $waitAttempts = 40,
        private readonly int $waitIntervalUs = 25_000,
    ) {
    }

    /**
     * 依据平台配置构造实例
     */
    public static function for(ConfigInterface $config): self
    {
        $cache = $config->get('cache');

        return new self(
            cache: $cache instanceof CacheInterface ? $cache : Cache::getInstance(),
            enabled: (bool) $config->get('token_cache', true),
            safetyMargin: max(0, (int) $config->get('token_safety_margin', self::DEFAULT_SAFETY_MARGIN)),
        );
    }

    /**
     * 读取缓存令牌，缺失时通过 resolver 刷新并写入缓存
     *
     * @param callable(): TokenResult $resolver
     */
    public function remember(
        Platform $platform,
        string $identity,
        string $scope,
        callable $resolver,
    ): mixed {
        if (!$this->enabled) {
            return $resolver()->value;
        }

        $key = $this->key($platform, $identity, $scope);

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        // 单飞：只允许一个进程去刷新
        if ($this->acquireLock($key)) {
            try {
                return $this->resolveAndStore($key, $resolver);
            } finally {
                $this->releaseLock($key);
            }
        }

        // 未抢到锁：短暂等待持锁进程写入缓存
        $waited = $this->waitForCache($key);
        if ($waited !== null) {
            return $waited;
        }

        // 等待超时则自行刷新，保证可用性优先
        return $this->resolveAndStore($key, $resolver);
    }

    /**
     * 强制刷新令牌（忽略现有缓存）
     *
     * @param callable(): TokenResult $resolver
     */
    public function refresh(
        Platform $platform,
        string $identity,
        string $scope,
        callable $resolver,
    ): mixed {
        $key = $this->key($platform, $identity, $scope);
        $this->cache->delete($key);

        if (!$this->enabled) {
            return $resolver()->value;
        }

        return $this->resolveAndStore($key, $resolver);
    }

    /**
     * 主动失效令牌缓存（收到 40001 等令牌失效错误码时调用）
     */
    public function forget(Platform $platform, string $identity, string $scope = 'access_token'): void
    {
        $this->cache->delete($this->key($platform, $identity, $scope));
    }

    /**
     * 是否已缓存
     */
    public function has(Platform $platform, string $identity, string $scope = 'access_token'): bool
    {
        return $this->cache->get($this->key($platform, $identity, $scope)) !== null;
    }

    /**
     * 缓存是否启用
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 生成缓存键（identity 做哈希，避免 PSR-16 保留字符与密钥外泄）
     */
    public function key(Platform $platform, string $identity, string $scope): string
    {
        return self::PREFIX . $platform->value . '_' . $scope . '_' . md5($identity);
    }

    /**
     * @param callable(): TokenResult $resolver
     */
    private function resolveAndStore(string $key, callable $resolver): mixed
    {
        $result = $resolver();
        $ttl    = $this->ttlFor($result->expiresIn);

        if ($result->value !== null && $ttl > 0) {
            $this->cache->set($key, $result->value, $ttl);
        }

        return $result->value;
    }

    /**
     * 计算实际缓存时长：平台有效期 - 安全边界，且不低于 MIN_TTL
     */
    private function ttlFor(int $expiresIn): int
    {
        if ($expiresIn <= 0) {
            $expiresIn = TokenResult::DEFAULT_EXPIRES_IN;
        }

        return max(self::MIN_TTL, $expiresIn - $this->safetyMargin);
    }

    private function acquireLock(string $key): bool
    {
        $lockKey = $key . self::LOCK_SUFFIX;

        if ($this->cache->get($lockKey) !== null) {
            return false;
        }

        $owner = bin2hex(random_bytes(8));
        $this->cache->set($lockKey, $owner, $this->lockTtl);

        // PSR-16 无原子 add 语义，回读比对做尽力而为的 CAS
        /** @var mixed $cached */
        $cached = $this->cache->get($lockKey);

        return is_string($cached) && $cached === $owner;
    }

    private function releaseLock(string $key): void
    {
        $this->cache->delete($key . self::LOCK_SUFFIX);
    }

    private function waitForCache(string $key): mixed
    {
        for ($i = 0; $i < $this->waitAttempts; $i++) {
            usleep($this->waitIntervalUs);

            $value = $this->cache->get($key);
            if ($value !== null) {
                return $value;
            }

            // 持锁进程已释放但仍无缓存，说明刷新失败，立即退出等待
            if ($this->cache->get($key . self::LOCK_SUFFIX) === null) {
                return null;
            }
        }

        return null;
    }
}
