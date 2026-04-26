<?php

declare(strict_types=1);

namespace Kode\MiniApp\Contracts;

/**
 * 应用接口，每个平台下的具体应用能力
 */
interface AppInterface
{
    /**
     * 获取应用名称
     */
    public function name(): string;

    /**
     * 获取所属平台
     */
    public function platform(): PlatformInterface;

    /**
     * 获取配置
     */
    public function config(): ConfigInterface;

    /**
     * 获取 HTTP 客户端
     */
    public function http(): HttpClientInterface;
}
