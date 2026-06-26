<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Bridge;

use Kode\MiniApp\Bridge\ToolsBridge;
use Kode\MiniApp\Tests\TestCase;

/**
 * ToolsBridge 桥接器测试
 * 验证未安装 kode/tools 时回退到内置工具类
 */
class ToolsBridgeTest extends TestCase
{
    public function testHasToolsPackageReturnsFalseWhenNotInstalled(): void
    {
        // kode/tools 未安装时返回 false
        self::assertFalse(ToolsBridge::hasToolsPackage());
    }

    public function testStrReturnsInternalClassWhenNotInstalled(): void
    {
        self::assertSame('Kode\MiniApp\Utils\Str', ToolsBridge::str());
    }

    public function testSignReturnsInternalClassWhenNotInstalled(): void
    {
        self::assertSame('Kode\MiniApp\Utils\Sign', ToolsBridge::sign());
    }

    public function testXmlReturnsInternalClassWhenNotInstalled(): void
    {
        self::assertSame('Kode\MiniApp\Utils\Xml', ToolsBridge::xml());
    }

    public function testCryptoReturnsNullWhenNotInstalled(): void
    {
        // 内置工具不提供加密能力，未安装 kode/tools 时应返回 null
        self::assertNull(ToolsBridge::crypto());
    }

    public function testQrcodeReturnsNullWhenNotInstalled(): void
    {
        // 内置工具不提供二维码能力，未安装 kode/tools 时应返回 null
        self::assertNull(ToolsBridge::qrcode());
    }

    public function testReturnedClassesAreCallable(): void
    {
        // 验证回退的内置工具类真实存在且可调用
        self::assertTrue(class_exists(ToolsBridge::str()));
        self::assertTrue(class_exists(ToolsBridge::sign()));
        self::assertTrue(class_exists(ToolsBridge::xml()));
    }
}
