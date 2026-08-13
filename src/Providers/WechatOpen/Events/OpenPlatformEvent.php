<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen\Events;

use JsonSerializable;

/**
 * 微信开放平台回调事件
 *
 * 由 {@see \Kode\MiniApp\Providers\WechatOpen\WechatOpenApp::notify()} 解密并构造，
 * 覆盖三类推送：
 *   - 授权事件接收 URL：component_verify_ticket / authorized / updateauthorized / unauthorized
 *   - 授权方消息与事件接收 URL：用户消息、菜单事件等（明文为 XML）
 *
 * 提供常见字段的类型化访问器；其余字段用 {@see self::get()} 取原始值。
 */
final readonly class OpenPlatformEvent implements JsonSerializable
{
    /**
     * @param array<string, mixed> $payload 解密后的原始数组（XML 解析结果）
     */
    public function __construct(private array $payload)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /**
     * 事件类型
     *
     * 授权事件推送为 InfoType；授权方消息为 MsgType。两者统一在此返回。
     */
    public function infoType(): ?string
    {
        return $this->stringOrNull(
            $this->payload['InfoType'] ?? $this->payload['infoType'] ?? $this->payload['MsgType'] ?? null
        );
    }

    /**
     * component_verify_ticket（每 10 分钟推送一次，用于换 component_access_token）
     */
    public function ticket(): ?string
    {
        return $this->stringOrNull($this->payload['ComponentVerifyTicket'] ?? null);
    }

    /**
     * 授权方 AppId（授权成功 / 取消 / 更新事件携带；授权方消息携带 AppId）
     */
    public function authorizerAppId(): ?string
    {
        return $this->stringOrNull($this->payload['AuthorizerAppid'] ?? $this->payload['AppId'] ?? null);
    }

    /**
     * 授权码（authorized / updateauthorized 事件携带，用于换 authorizer_access_token）
     */
    public function authorizationCode(): ?string
    {
        return $this->stringOrNull($this->payload['AuthorizationCode'] ?? null);
    }

    /**
     * 授权码过期时间（Unix 时间戳）
     */
    public function authorizationCodeExpiredAt(): ?int
    {
        $value = $this->payload['AuthorizationCodeExpiredTime'] ?? null;

        return $value !== null ? (int) $value : null;
    }

    /**
     * 事件名（如 subscribe / CLICK / authorized 等）
     */
    public function event(): ?string
    {
        return $this->stringOrNull($this->payload['Event'] ?? null);
    }

    /**
     * 取原始字段，访问未知字段时返回默认值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->payload;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;

        return $string === '' ? null : $string;
    }
}
