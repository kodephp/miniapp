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
}
