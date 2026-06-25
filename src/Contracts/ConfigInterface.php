<?php

declare(strict_types=1);

namespace Kode\MiniApp\Contracts;

/**
 * 配置接口，所有平台配置必须实现
 */
interface ConfigInterface
{
    /**
     * 获取平台类型
     */
    public function platform(): Platform;

    /**
     * 获取应用 ID（appid、client_id 等）
     */
    public function appId(): string;

    /**
     * 获取应用密钥
     */
    public function secret(): string;

    /**
     * 获取原始配置数组
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * 获取单个配置项
     *
     * @template T
     * @param T $default
     * @return T
     */
    public function get(string $key, mixed $default = null): mixed;
}
