<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Exceptions\ConfigException;

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

    #[\Override]
    public function platform(): Platform
    {
        return $this->platform;
    }

    #[\Override]
    public function appId(): string
    {
        return (string) ($this->data['app_id'] ?? $this->data['appid'] ?? '');
    }

    #[\Override]
    public function secret(): string
    {
        return (string) ($this->data['secret'] ?? $this->data['app_secret'] ?? '');
    }

    #[\Override]
    public function all(): array
    {
        return $this->data;
    }

    /**
     * 获取单个配置项
     *
     * @template T
     * @param T $default
     * @return T
     */
    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * 平台级必填配置键（默认无，子类按需覆写）
     *
     * @return array<string>
     */
    #[\Override]
    public function requiredKeys(): array
    {
        return [];
    }

    /**
     * 特定能力的额外必填配置键（默认无）
     *
     * @return array<string>
     */
    #[\Override]
    public function requiredKeysFor(ChannelFeature $feature): array
    {
        return [];
    }

    /**
     * 校验平台级必填配置，缺失时抛清晰异常
     */
    #[\Override]
    public function validate(): void
    {
        $this->assertKeys($this->requiredKeys(), '基础');
    }

    /**
     * 校验特定能力所需的必填配置，缺失时抛清晰异常
     */
    #[\Override]
    public function validateFeature(ChannelFeature $feature): void
    {
        $this->assertKeys($this->requiredKeysFor($feature), $feature->label());
    }

    /**
     * 校验给定键是否存在，缺失则按作用域抛出 ConfigException
     *
     * @param array<string> $keys
     */
    private function assertKeys(array $keys, string $scope): void
    {
        if ($keys === []) {
            return;
        }

        $missing = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->data)) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new ConfigException(sprintf(
                '[%s] 配置缺失（%s）必填项：%s',
                $this->platform->label(),
                $scope,
                implode(', ', $missing),
            ));
        }
    }
}
