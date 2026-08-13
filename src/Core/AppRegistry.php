<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\Platform;

/**
 * 应用注册表（多小程序 / 多公众号 / 多租户按 appid 路由）
 *
 * 包默认「一个 Kernel = 一个 appid」。当自研业务同时持有多个小程序 /
 * 公众号（通常均绑定同一微信开放平台）时，可为每个 appid 各自 new Kernel
 * 并注册到本表，运行时按 appid 解析出对应平台的 App 实例，实现「按 appid
 * 运行时切换」而无需改动包内核。
 *
 * 用法：
 *   $registry = new AppRegistry();
 *   $registry->register('wxa111', new Kernel($cfgMiniA));
 *   $registry->register('wxb222', new Kernel($cfgMiniB));
 *
 *   $app = $registry->app('wxa111');                  // 默认微信平台 App
 *   $app = $registry->app('wxb222', Platform::Wechat);
 *
 * 注：本类是当前「一个 Kernel = 一个 appid」模型下的轻量索引层。若需要
 * 「单个 Kernel 直接管理多个 appid 配置」的核心重构（配置模型变更），属
 * 更大范围的架构改造，应单独评估，不在本类职责内。
 */
final class AppRegistry
{
    /** @var array<string, KernelInterface> */
    private array $kernels = [];

    public function register(string $appId, KernelInterface $kernel): self
    {
        $this->kernels[$appId] = $kernel;

        return $this;
    }

    public function has(string $appId): bool
    {
        return isset($this->kernels[$appId]);
    }

    /**
     * 解析 appid 对应的 Kernel
     *
     * @throws \RuntimeException 未注册时
     */
    public function kernel(string $appId): KernelInterface
    {
        if (!isset($this->kernels[$appId])) {
            throw new \RuntimeException("应用注册表中未找到 appid: {$appId}");
        }

        return $this->kernels[$appId];
    }

    /**
     * 解析 appid 对应的平台 App 实例
     *
     * @throws \RuntimeException 未注册时
     */
    public function app(string $appId, Platform $platform = Platform::Wechat): AppInterface
    {
        return $this->kernel($appId)->app($platform);
    }

    /**
     * @return array<string, KernelInterface>
     */
    public function all(): array
    {
        return $this->kernels;
    }
}
