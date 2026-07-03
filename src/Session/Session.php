<?php

declare(strict_types=1);

namespace Kode\MiniApp\Session;

/**
 * 会话数据模型
 *
 * 表示一个登录会话的所有上下文信息，用于多端登录约束。
 * 业务侧通常在登录成功后创建 Session，登出时销毁。
 */
final readonly class Session
{
    /**
     * @param array<string, mixed> $payload 业务自定义载荷（角色、权限等）
     */
    public function __construct(
        /**
         * 会话唯一 ID（对应 JWT 的 jti）
         */
        public string $id,
        /**
         * 跨平台统一 ID（同一开放平台下所有应用共享）
         */
        public string $unionId,
        /**
         * 平台内 OpenID
         */
        public string $openId,
        /**
         * 登录渠道（如 Channel::WechatMini）
         */
        public \Kode\MiniApp\Union\Channel $channel,
        /**
         * 端口场景（mini / mp / h5 / pc / app / open）
         */
        public string $scene,
        /**
         * 客户端类型（web / ios / android / mp / mini / pc）
         */
        public string $client,
        /**
         * 客户端唯一 ID（设备指纹，可选；如不传则按 client 单点约束）
         */
        public string $clientId,
        /**
         * 登录 IP
         */
        public string $ip,
        /**
         * User-Agent
         */
        public string $userAgent,
        /**
         * 创建时间（Unix 时间戳）
         */
        public int $createdAt,
        /**
         * 过期时间（Unix 时间戳）
         */
        public int $expiresAt,
        /**
         * 业务自定义载荷
         *
         * @var array<string, mixed>
         */
        public array $payload = [],
    ) {
    }

    /**
     * 是否已过期
     */
    public function isExpired(?int $now = null): bool
    {
        $now ??= time();
        return $this->expiresAt <= $now;
    }

    /**
     * 距离过期还有多少秒（已过期返回负数）
     */
    public function ttl(?int $now = null): int
    {
        $now ??= time();
        return $this->expiresAt - $now;
    }

    /**
     * 续期到新的过期时间
     */
    public function withExpiresAt(int $expiresAt): self
    {
        return new self(
            id:         $this->id,
            unionId:    $this->unionId,
            openId:     $this->openId,
            channel:    $this->channel,
            scene:      $this->scene,
            client:     $this->client,
            clientId:   $this->clientId,
            ip:         $this->ip,
            userAgent:  $this->userAgent,
            createdAt:  $this->createdAt,
            expiresAt:  $expiresAt,
            payload:    $this->payload,
        );
    }

    /**
     * 转为数组（用于序列化到存储）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'unionId'    => $this->unionId,
            'openId'     => $this->openId,
            'channel'    => $this->channel->value,
            'scene'      => $this->scene,
            'client'     => $this->client,
            'clientId'   => $this->clientId,
            'ip'         => $this->ip,
            'userAgent'  => $this->userAgent,
            'createdAt'  => $this->createdAt,
            'expiresAt'  => $this->expiresAt,
            'payload'    => $this->payload,
        ];
    }

    /**
     * 从数组还原 Session 实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $channel = \Kode\MiniApp\Union\Channel::from((string) ($data['channel'] ?? ''));

        return new self(
            id:         (string) ($data['id'] ?? ''),
            unionId:    (string) ($data['unionId'] ?? ''),
            openId:     (string) ($data['openId'] ?? ''),
            channel:    $channel,
            scene:      (string) ($data['scene'] ?? ''),
            client:     (string) ($data['client'] ?? ''),
            clientId:   (string) ($data['clientId'] ?? ''),
            ip:         (string) ($data['ip'] ?? ''),
            userAgent:  (string) ($data['userAgent'] ?? ''),
            createdAt:  (int) ($data['createdAt'] ?? time()),
            expiresAt:  (int) ($data['expiresAt'] ?? time()),
            payload:    is_array($data['payload'] ?? null) ? $data['payload'] : [],
        );
    }
}
