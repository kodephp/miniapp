<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union;

use Kode\MiniApp\Contracts\ChannelFeature;

/**
 * 渠道能力发现结果
 *
 * 由 {@see Union::capabilities()} 返回，集中暴露某渠道「支持哪些能力」与
 * 「启用这些能力需要哪些配置」，便于开发者在运行前自检，避免撞运行时异常。
 */
final class CapabilityInfo
{
    /**
     * @param array<ChannelFeature> $features      该渠道支持的能力
     * @param array<string>         $requiredConfig 启用这些能力所需的必填配置键（去重）
     */
    public function __construct(
        public readonly Channel $channel,
        public readonly array $features,
        public readonly array $requiredConfig,
    ) {
    }

    /**
     * 是否支持指定能力
     */
    public function supports(ChannelFeature $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    /**
     * 转为可序列化数组（便于日志 / 调试 / 前端展示）
     *
     * @return array{channel:string, label:string, features:array<string>, required_config:array<string>}
     */
    public function toArray(): array
    {
        return [
            'channel'         => $this->channel->value,
            'label'           => $this->channel->label(),
            'features'        => array_map(static fn (ChannelFeature $f) => $f->value, $this->features),
            'required_config' => $this->requiredConfig,
        ];
    }
}
