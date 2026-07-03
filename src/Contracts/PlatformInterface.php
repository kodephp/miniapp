<?php

declare(strict_types=1);

namespace Kode\MiniApp\Contracts;

/**
 * 平台 Provider 接口
 */
interface PlatformInterface
{
    /**
     * 获取平台标识
     */
    public function name(): Platform;

    /**
     * 获取平台下指定名称的应用实例
     */
    public function app(string $name = 'default'): AppInterface;

    /**
     * 获取 HTTP 客户端
     */
    public function http(): HttpClientInterface;

    /**
     * 获取配置
     */
    public function config(): ConfigInterface;

    /**
     * 获取关联的 Kernel 实例（用于跨平台 Provider 互联）
     *
     * 当 Provider 由 Kernel 创建时，会自动注入 Kernel 实例。
     * 业务侧可基于此实现 Provider 之间的桥接（如微信生态关联）。
     */
    public function kernel(): ?KernelInterface;
}
