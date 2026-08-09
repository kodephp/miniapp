<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Kode\MiniApp\Contracts\ConfigInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * session_key 托管管理器（登录即缓存，供客户端数据解密一站式复用）
 *
 * 解决痛点：微信 / 抖音 / QQ 小程序 code2session 返回的 session_key 是解密
 * encryptedData 的必备密钥，但 SDK 历史上只「返回」不「托管」。业务侧每解密一次
 * 都得自己持久化并回传 session_key，极易因前后端传递出错而解密失败。
 *
 * 用法（自动）：Auth::session() 登录成功后会自动把 session_key 按 openid 存入缓存，
 * 之后解密只需传 openid（无需再传 session_key）：
 *   $user   = Union::wechat()->mini($code);                                  // 登录即托管 session_key
 *   $phone  = Union::decryptByUser(Channel::WechatMini, $encryptedData, $iv, $user->openId);
 *
 * 用法（手动）：
 *   SessionKeyManager::for($config)->store($openId, $sessionKey);
 *   $sk     = SessionKeyManager::for($config)->get($openId);
 *   SessionKeyManager::for($config)->forget($openId);
 *
 * 设计取舍：
 *   - 与 AccessToken 的 TokenManager 共用同一套 PSR-16 + 配置约定，行为一致。
 *   - session_key 按 openid 维度缓存；重新登录会自动覆盖旧值。
 *   - 默认不过期（ttl=null）；平台侧注销 / session 失效后，调用方可用 forget() 清缓存，
 *     或在配置里设 session_key_ttl 限定有效期。
 *   - session_key 属敏感凭证，已纳入 LogSanitizer 脱敏；缓存层同样不应明文落日志。
 *
 * 配置项（写在平台配置数组中）：
 *   'cache'             => PSR-16 实例，默认走 Cache::getInstance()
 *   'session_key_cache' => false 可关闭托管（调试 / 不想落缓存时）
 *   'session_key_ttl'   => 缓存秒数，默认 null（不过期，重新登录会覆盖）
 */
final class SessionKeyManager
{
    /**
     * 缓存键前缀
     */
    public const string PREFIX = 'kode_miniapp_session_key_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly bool $enabled = true,
        private readonly ?int $ttl = null,
    ) {
    }

    /**
     * 依据平台配置构造实例
     */
    public static function for(ConfigInterface $config): self
    {
        $cache = $config->get('cache');

        $ttl = $config->get('session_key_ttl');
        $ttl = $ttl === null ? null : (int) $ttl;

        return new self(
            cache:   $cache instanceof CacheInterface ? $cache : Cache::getInstance(),
            enabled: (bool) $config->get('session_key_cache', true),
            ttl:     $ttl,
        );
    }

    /**
     * 托管某用户的 session_key
     */
    public function store(string $openId, string $sessionKey, ?int $ttl = null): void
    {
        if (!$this->enabled || $openId === '' || $sessionKey === '') {
            return;
        }

        $this->cache->set($this->key($openId), $sessionKey, $ttl ?? $this->ttl);
    }

    /**
     * 取回某用户的 session_key（未托管 / 已过期返回 null）
     */
    public function get(string $openId): ?string
    {
        if (!$this->enabled || $openId === '') {
            return null;
        }

        $value = $this->cache->get($this->key($openId));

        return is_string($value) ? $value : null;
    }

    /**
     * 清除某用户的 session_key 缓存
     */
    public function forget(string $openId): void
    {
        if ($openId === '') {
            return;
        }

        $this->cache->delete($this->key($openId));
    }

    /**
     * 是否已托管该用户的 session_key
     */
    public function has(string $openId): bool
    {
        return $this->get($openId) !== null;
    }

    /**
     * 托管是否启用
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 生成缓存键（openid 做哈希，避免 PSR-16 保留字符与密钥外泄）
     */
    public function key(string $openId): string
    {
        return self::PREFIX . md5($openId);
    }
}
