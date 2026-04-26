<?php

declare(strict_types=1);

namespace Kode\MiniApp\Bridge;

use Kode\MiniApp\Contracts\AppInterface;

/**
 * 支付桥接器
 * 当安装 kode/pays 包时，可通过此类桥接到企业级支付能力
 * 未安装时保持内置基础支付能力
 */
final class PayBridge
{
    private static ?bool $hasPayPackage = null;

    /**
     * 检查是否安装了 kode/pays 包
     */
    public static function hasPayPackage(): bool
    {
        if (self::$hasPayPackage === null) {
            self::$hasPayPackage = class_exists('Kode\Pays\Pay');
        }

        return self::$hasPayPackage;
    }

    /**
     * 获取支付实例
     * 如果安装了 kode/pays，返回其支付实例；否则返回 null
     */
    public static function getPay(AppInterface $app): ?object
    {
        if (!self::hasPayPackage()) {
            return null;
        }

        $config = $app->config()->all();
        $platform = $app->platform()->name()->value;

        // 桥接到 kode/pays 的支付工厂
        return \Kode\Pays\Pay::factory($platform, $config);
    }

    /**
     * 获取支付通知处理器
     */
    public static function getNotify(AppInterface $app): ?object
    {
        if (!self::hasPayPackage()) {
            return null;
        }

        $config = $app->config()->all();
        $platform = $app->platform()->name()->value;

        return \Kode\Pays\Pay::notify($platform, $config);
    }
}
