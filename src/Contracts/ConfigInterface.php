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

    /**
     * 平台级必填配置键（任一使用该 Provider 都需提供）
     *
     * @return array<string>
     */
    public function requiredKeys(): array;

    /**
     * 特定能力所需的额外必填配置键
     *
     * 例如微信在启用支付时还需 mch_id / key_path / mch_serial_no。
     * 默认返回空数组（无额外要求）。
     *
     * @return array<string>
     */
    public function requiredKeysFor(ChannelFeature $feature): array;

    /**
     * 校验平台级必填配置，缺失时抛出清晰异常
     */
    public function validate(): void;

    /**
     * 校验特定能力所需的必填配置，缺失时抛出清晰异常
     */
    public function validateFeature(ChannelFeature $feature): void;
}
