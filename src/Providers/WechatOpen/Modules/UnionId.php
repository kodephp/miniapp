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
     * 判断该 unionid 是否属于「当前第三方平台所管理的授权方」。
     *
     * 说明：微信的 unionid 本身是随机串，无法仅凭字符串判断归属。
     * 真正可靠的判定是「该 unionid 是否来自你管理的某个授权方（公众号 / 小程序）」。
     * 因此本方法在提供已知授权方 appid 集合时，校验 payload 中的
     * authorizer_appid / appid 是否在集合内；未提供集合时退化为「仅判断 unionid 存在」，
     * 此时调用方需自行比对授权方清单（{@see Component::allAuthorizers()}）。
     *
     * @param array<string, mixed> $payload
     * @param array<int, string>   $knownAuthorizerAppIds 当前第三方平台管理的授权方 appid 集合
     */
    public function belongsToCurrent(array $payload, array $knownAuthorizerAppIds = []): bool
    {
        if ($this->fromPayload($payload) === null) {
            return false;
        }

        if ($knownAuthorizerAppIds === []) {
            return true;
        }

        $appId = $payload['authorizer_appid'] ?? $payload['appid'] ?? null;

        return is_string($appId) && in_array($appId, $knownAuthorizerAppIds, true);
    }

    /**
     * 构造 UnionID 缓存键
     */
    public function cacheKey(string $unionId, string $scope = 'default'): string
    {
        return self::CACHE_PREFIX . $scope . '_' . $unionId;
    }
}
