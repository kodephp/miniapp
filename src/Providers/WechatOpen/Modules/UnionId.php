<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen\Modules;

use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;

/**
 * UnionID 辅助模块
 *
 * 同一微信开放平台账号下的多个应用（公众号、小程序、移动 App、网站应用）
 * 拥有相同的 unionid。SDK 在各业务场景下统一暴露 UnionID 处理工具。
 */
readonly class UnionId
{
    /**
     * 缓存键前缀，可被业务代码覆盖
     */
    private const CACHE_PREFIX = 'kode_wechat_unionid_';

    public function __construct(
        private WechatOpenApp $app,
    ) {
    }

    /**
     * 获取绑定的应用实例
     */
    public function app(): WechatOpenApp
    {
        return $this->app;
    }

    /**
     * 从某个授权方调用结果中提取 unionid
     *
     * @param array<string, mixed> $payload
     */
    public function fromPayload(array $payload): ?string
    {
        $value = $payload['unionid'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * 是否属于当前开放平台账号下（通过 unionid 前缀或业务字段判断）
     *
     * @param array<string, mixed> $payload
     */
    public function belongsToCurrent(array $payload): bool
    {
        return $this->fromPayload($payload) !== null;
    }

    /**
     * 构造 UnionID 缓存键
     */
    public function cacheKey(string $unionId, string $scope = 'default'): string
    {
        return self::CACHE_PREFIX . $scope . '_' . $unionId;
    }
}
