<?php

declare(strict_types=1);

namespace Kode\MiniApp\Bridge;

/**
 * 工具包桥接器
 * 当安装 kode/tools 包时，优先使用其工具类
 * 未安装时使用内置工具类
 */
final class ToolsBridge
{
    private static ?bool $hasToolsPackage = null;

    /**
     * 检查是否安装了 kode/tools 包
     */
    public static function hasToolsPackage(): bool
    {
        if (self::$hasToolsPackage === null) {
            self::$hasToolsPackage = class_exists('Kode\Tools\Str');
        }

        return self::$hasToolsPackage;
    }

    /**
     * 获取字符串工具类
     */
    public static function str(): string
    {
        return self::hasToolsPackage()
            ? 'Kode\Tools\Str'
            : 'Kode\MiniApp\Utils\Str';
    }

    /**
     * 获取签名工具类
     */
    public static function sign(): string
    {
        return self::hasToolsPackage()
            ? 'Kode\Tools\Sign'
            : 'Kode\MiniApp\Utils\Sign';
    }

    /**
     * 获取 XML 工具类
     */
    public static function xml(): string
    {
        return self::hasToolsPackage()
            ? 'Kode\Tools\Xml'
            : 'Kode\MiniApp\Utils\Xml';
    }

    /**
     * 获取加密工具类
     */
    public static function crypto(): ?string
    {
        return self::hasToolsPackage()
            ? 'Kode\Tools\Crypto'
            : null;
    }

    /**
     * 获取二维码工具类
     */
    public static function qrcode(): ?string
    {
        return self::hasToolsPackage()
            ? 'Kode\Tools\QrCode'
            : null;
    }
}
