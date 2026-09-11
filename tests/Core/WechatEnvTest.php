<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Core\WechatEnv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WechatEnvTest extends TestCase
{
    /**
     * @param callable(string): bool $detector
     * @param array<string, bool>    $expectations UA → 期望结果
     */
    #[DataProvider('detectorProvider')]
    public function testDetector(callable $detector, array $expectations): void
    {
        foreach ($expectations as $ua => $expected) {
            self::assertSame($expected, $detector($ua), "UA [{$ua}] 判定结果与预期不符");
        }
    }

    public static function detectorProvider(): \Generator
    {
        yield 'isWechatBrowser' => [
            static fn (string $ua): bool => WechatEnv::isWechatBrowser($ua),
            [
                // 微信内置（Android/iOS 典型 UA，大小写不敏感）
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 MicroMessenger/8.0.49' => true,
                'mozilla/5.0 (linux; android 13) applewebkit/537.36 mobile micromessenger/8.0.49'                                 => true,
                // 企业微信内置（wxwork）
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) wxwork/4.1.16 MicroMessenger/8.0.31'                      => true,
                // 微信外浏览器
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Safari/605.1.15'                              => false,
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0.0.0 Safari/537.36'                                        => false,
                // 空串安全
                ''                                                                                                                => false,
            ],
        ];

        yield 'isWechatWork' => [
            static fn (string $ua): bool => WechatEnv::isWechatWork($ua),
            [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) wxwork/4.1.16 MicroMessenger/8.0.31' => true,
                'WxWork/4.1.16'                                                                             => true,
                'Mozilla/5.0 MicroMessenger/8.0.49'                                                          => false,
                'Mozilla/5.0 Safari/605.1.15'                                                               => false,
                ''                                                                                          => false,
            ],
        ];
    }
}
