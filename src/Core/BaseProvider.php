<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;

/**
 * Provider 抽象基类
 *
 * 提供 Kernel 关联、共享 HTTP 客户端等通用能力。
 * 所有具体平台 Provider 都应继承本类，避免重复实现。
 */
abstract class BaseProvider implements PlatformInterface
{
    protected ?KernelInterface $kernel = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected readonly array $config,
        protected readonly HttpClientInterface $http,
        ?KernelInterface $kernel = null,
    ) {
        $this->kernel = $kernel;
    }

    /**
     * 获取平台标识
     */
    abstract public function name(): Platform;

    /**
     * 获取平台下指定名称的应用实例
     */
    abstract public function app(string $name = 'default'): AppInterface;

    /**
     * 获取 HTTP 客户端
     */
    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    /**
     * 获取平台配置
     */
    abstract public function config(): ConfigInterface;

    /**
     * 获取关联的 Kernel 实例
     */
    public function kernel(): ?KernelInterface
    {
        return $this->kernel;
    }
}
