<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\Platform;

/**
 * 基础配置类，所有平台配置继承此类
 * 使用 readonly 保证不可变性
 */
readonly class BaseConfig implements ConfigInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private Platform $platform,
        private array $data,
    ) {
    }

    public function platform(): Platform
    {
        return $this->platform;
    }

    public function appId(): string
    {
        return (string) ($this->data['app_id'] ?? $this->data['appid'] ?? '');
    }

    public function secret(): string
    {
        return (string) ($this->data['secret'] ?? $this->data['app_secret'] ?? '');
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
