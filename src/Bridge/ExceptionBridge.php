<?php

declare(strict_types=1);

namespace Kode\MiniApp\Bridge;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Exceptions\MiniAppException;
use Kode\MiniApp\Exceptions\PlatformException;

/**
 * 异常桥接器
 * 当安装 kode/exception 包时，可扩展异常处理能力
 * 未安装时使用内置异常体系
 */
final class ExceptionBridge
{
    private static ?bool $hasExceptionPackage = null;

    /**
     * 检查是否安装了 kode/exception 包
     */
    public static function hasExceptionPackage(): bool
    {
        if (self::$hasExceptionPackage === null) {
            self::$hasExceptionPackage = class_exists('Kode\Exception\ExceptionHandler');
        }

        return self::$hasExceptionPackage;
    }

    /**
     * 包装异常
     * 如果安装了 kode/exception，使用其异常码体系；否则使用内置异常
     */
    public static function wrap(
        string $message,
        Platform $platform,
        int $code = 0,
        ?\Throwable $previous = null,
    ): MiniAppException {
        if (self::hasExceptionPackage()) {
            // 使用 kode/exception 的异常码体系
            $exceptionCode = self::mapPlatformCode($platform, $code);

            return new PlatformException($message, $platform, $exceptionCode, $previous);
        }

        return new PlatformException($message, $platform, $code, $previous);
    }

    /**
     * 映射平台异常码
     */
    private static function mapPlatformCode(Platform $platform, int $code): int
    {
        $base = match ($platform) {
            Platform::Wechat     => 100000,
            Platform::WechatOpen => 110000,
            Platform::Alipay     => 200000,
            Platform::Douyin     => 300000,
            Platform::Baidu      => 400000,
            Platform::Qq         => 500000,
            Platform::WechatWork => 600000,
            Platform::Dingtalk   => 700000,
            Platform::Lark       => 800000,
        };

        return $base + $code;
    }
}
