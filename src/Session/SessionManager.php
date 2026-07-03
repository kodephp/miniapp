<?php

declare(strict_types=1);

namespace Kode\MiniApp\Session;

use InvalidArgumentException;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 会话管理器
 *
 * 解决"一个用户多端登录"的业务约束问题。
 *
 * 与 kode/jwt 的关系：
 *   - kode/jwt：负责 token 的签发/验证/刷新（stateless，无状态）
 *   - 本类：  负责会话/登录端约束（stateful，有状态）
 * 业务侧典型组合用法：
 *   1. 用 SessionManager 创建会话（应用登录约束）
 *   2. 用 kode/jwt 把 session.id 作为 jti 签发 JWT
 *   3. 每次请求用 JWT 验证 token，再用 SessionManager 验证会话有效性
 *
 * 用法：
 *   $manager = new SessionManager(new CacheSessionStorage($psr16Cache));
 *   $manager->withPolicy(SessionPolicy::SingleAll);  // 全端唯一
 *
 *   $session = $manager->create($user, 'mini', 'ios', $deviceId);
 *   $manager->destroy($session->id);   // 主动登出
 *   $manager->destroyByClient($unionId, 'ios', $deviceId);  // 踢掉某端
 *   $manager->destroyAll($unionId);    // 踢掉所有端
 */
final class SessionManager
{
    private SessionPolicy $policy;

    /**
     * @var array<string, Session> 当前 manager 进程内已知的会话缓存（避免频繁读取存储）
     */
    private array $localCache = [];

    public function __construct(
        private readonly SessionStorageInterface $storage,
        SessionPolicy $policy = SessionPolicy::Multi,
        private readonly int $ttl = 86400 * 30,
    ) {
        $this->policy = $policy;
    }

    /**
     * 设置登录约束策略
     */
    public function withPolicy(SessionPolicy $policy): self
    {
        $clone = clone $this;
        $clone->policy = $policy;
        return $clone;
    }

    /**
     * 当前策略
     */
    public function policy(): SessionPolicy
    {
        return $this->policy;
    }

    /**
     * 创建会话
     *
     * 创建前会根据当前 policy 自动踢掉冲突的会话。
     *
     * @param array<string, mixed> $payload 业务自定义载荷
     */
    public function create(
        UnionUser $user,
        string $scene,
        string $client = 'web',
        string $clientId = '',
        string $ip = '',
        string $userAgent = '',
        array $payload = [],
        ?int $ttl = null,
    ): Session {
        $now = time();
        $ttl = $ttl ?? $this->ttl;
        $union = $user->unionId !== '' ? $user->unionId : $user->openId;

        // 1. 根据策略踢掉冲突会话
        $this->evictConflicts($union, $user->channel, $scene, $client, $clientId);

        // 2. 创建新会话
        $session = new Session(
            id:        $this->generateId(),
            unionId:   $union,
            openId:    $user->openId,
            channel:   $user->channel,
            scene:     $scene,
            client:    $client,
            clientId:  $clientId,
            ip:        $ip,
            userAgent: $userAgent,
            createdAt: $now,
            expiresAt: $now + $ttl,
            payload:   $payload,
        );

        // 3. 持久化
        $this->storage->write($session->id, $session->toArray(), $ttl);
        $this->localCache[$session->id] = $session;

        return $session;
    }

    /**
     * 获取会话
     */
    public function get(string $sessionId): ?Session
    {
        if (isset($this->localCache[$sessionId])) {
            return $this->localCache[$sessionId];
        }
        $data = $this->storage->read($sessionId);
        if ($data === null) {
            return null;
        }
        $session = Session::fromArray($data);
        if ($session->isExpired()) {
            $this->destroy($sessionId);
            return null;
        }
        $this->localCache[$sessionId] = $session;
        return $session;
    }

    /**
     * 通过会话 ID 列表获取所有该 unionId 的活跃会话
     *
     * @return array<int, Session>
     */
    public function listByUnionId(string $unionId): array
    {
        $ids = $this->storage->findByIndex("u:{$unionId}");
        $sessions = [];
        foreach ($ids as $id) {
            $session = $this->get($id);
            if ($session !== null && !$session->isExpired()) {
                $sessions[] = $session;
            }
        }
        return $sessions;
    }

    /**
     * 续期
     */
    public function touch(string $sessionId, ?int $ttl = null): ?Session
    {
        $session = $this->get($sessionId);
        if ($session === null) {
            return null;
        }
        $ttl    = $ttl ?? $this->ttl;
        $newOne = $session->withExpiresAt(time() + $ttl);
        $this->storage->write($newOne->id, $newOne->toArray(), $ttl);
        $this->localCache[$newOne->id] = $newOne;
        return $newOne;
    }

    /**
     * 销毁单个会话（主动登出）
     */
    public function destroy(string $sessionId): void
    {
        $this->storage->delete($sessionId);
        unset($this->localCache[$sessionId]);
    }

    /**
     * 销毁某 unionId + 客户端的所有会话
     */
    public function destroyByClient(string $unionId, string $client, string $clientId = ''): int
    {
        $indexKey = $clientId !== '' ? "c:{$client}:{$clientId}" : "u:{$unionId}";
        $ids = $this->storage->findByIndex($indexKey);
        $count = 0;
        foreach ($ids as $id) {
            $session = $this->get($id);
            if ($session !== null && $session->unionId === $unionId) {
                $this->destroy($id);
                $count++;
            }
        }
        return $count;
    }

    /**
     * 销毁某 unionId 的所有会话（强制踢下线）
     */
    public function destroyAll(string $unionId): int
    {
        $sessions = $this->listByUnionId($unionId);
        $count = 0;
        foreach ($sessions as $session) {
            $this->destroy($session->id);
            $count++;
        }
        return $count;
    }

    /**
     * 根据策略踢掉冲突会话
     */
    private function evictConflicts(
        string $unionId,
        Channel $channel,
        string $scene,
        string $client,
        string $clientId,
    ): void {
        match ($this->policy) {
            SessionPolicy::Multi      => null,
            SessionPolicy::SingleEnd  => $this->evictByClient($client, $clientId),
            SessionPolicy::SingleUser => $this->destroyByScene($unionId, $scene),
            SessionPolicy::SingleAll  => $this->destroyAll($unionId),
        };
    }

    /**
     * 销毁同 client (设备) 的所有 session
     *
     * 与 destroyByClient 的区别：本方法不限定 unionId，
     * 用于实现 SingleEnd 策略（同设备只能登录 1 个账号）。
     */
    private function evictByClient(string $client, string $clientId): int
    {
        $ids = $this->storage->findByIndex("c:{$client}:{$clientId}");
        $count = 0;
        foreach ($ids as $id) {
            $this->destroy($id);
            $count++;
        }
        return $count;
    }

    /**
     * 销毁同 unionId + scene 的所有会话
     */
    private function destroyByScene(string $unionId, string $scene): int
    {
        $ids = $this->storage->findByIndex("s:{$unionId}:{$scene}");
        $count = 0;
        foreach ($ids as $id) {
            $session = $this->get($id);
            if ($session !== null && $session->scene === $scene) {
                $this->destroy($id);
                $count++;
            }
        }
        return $count;
    }

    /**
     * 生成会话 ID
     */
    private function generateId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('无法生成安全的会话 ID：' . $e->getMessage());
        }
    }
}
