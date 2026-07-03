<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels;

use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Union\Channel;

/**
 * 适配器抽象基类
 *
 * 提供 Kernel 访问器、Channel 解析等通用能力。
 * 所有 Channel 适配器都应继承本类。
 */
abstract class BaseAdapter
{
    public function __construct(
        protected readonly KernelInterface $kernel,
    ) {
    }

    /**
     * 当前适配器负责的渠道（子类必须实现）
     */
    abstract public function channel(): Channel;

    /**
     * 取一个具体平台 Provider（子类使用）
     */
    protected function provider(string $method): \Kode\MiniApp\Contracts\PlatformInterface
    {
        $provider = $this->kernel->{$method}();
        if (!$provider instanceof \Kode\MiniApp\Contracts\PlatformInterface) {
            throw new \RuntimeException("平台 Provider [{$method}] 不存在");
        }

        return $provider;
    }

    /**
     * 从数组中取字符串
     *
     * @param array<string, mixed> $data
     */
    protected static function str(array $data, string $key): string
    {
        $value = $data[$key] ?? '';
        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    /**
     * 从数组中取可选字符串
     *
     * @param array<string, mixed> $data
     */
    protected static function strOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }
        $str = (string) $value;
        return $str === '' ? null : $str;
    }

    /**
     * 校验必填字符串参数
     *
     * @param array<string, mixed> $payload
     */
    protected static function requireString(array $payload, string $key): string
    {
        if (!isset($payload[$key]) || !is_string($payload[$key]) || $payload[$key] === '') {
            throw new \InvalidArgumentException("缺少必填参数: {$key}");
        }
        return $payload[$key];
    }
}
