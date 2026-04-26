<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * 缓存工厂，默认使用文件系统缓存
 */
final class Cache
{
    private static ?CacheInterface $instance = null;

    public static function getInstance(?string $path = null): CacheInterface
    {
        if (self::$instance === null) {
            $adapter = new FilesystemAdapter(
                namespace: 'kode_miniapp',
                defaultLifetime: 7200,
                directory: $path ?? sys_get_temp_dir() . '/kode-miniapp-cache'
            );
            self::$instance = new Psr16Cache($adapter);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
