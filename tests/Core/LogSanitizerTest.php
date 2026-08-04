<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Core\LogSanitizer;
use Kode\MiniApp\Tests\TestCase;

/**
 * 日志脱敏 LogSanitizer 测试
 */
class LogSanitizerTest extends TestCase
{
    public function testUriQueryMasked(): void
    {
        $uri = 'https://api.weixin.qq.com/cgi-bin/token?appid=wx123&secret=abcdef123456';
        $safe = LogSanitizer::uri($uri);
        self::assertStringNotContainsString('abcdef123456', $safe);
        // * 会被 http_build_query 编码为 %2A
        self::assertStringContainsString('secret=ab%2A%2A%2A%2A56', $safe);
        // appid 不是敏感字段，保留原值
        self::assertStringContainsString('appid=wx123', $safe);
    }

    public function testUriWithoutQueryUnchanged(): void
    {
        self::assertSame('https://example.com/x', LogSanitizer::uri('https://example.com/x'));
    }

    public function testHeadersMasked(): void
    {
        $headers = [
            'Authorization' => 'Bearer secret-token-value',
            'X-Request-Id'  => 'abc',
        ];
        $safe = LogSanitizer::headers($headers);
        self::assertIsString($safe['Authorization']);
        self::assertStringContainsString('Be****ue', $safe['Authorization']);
        self::assertSame('abc', $safe['X-Request-Id']);
    }

    public function testArrayValuesMasked(): void
    {
        $data = [
            'access_token' => 'tok-very-secret',
            'openid'       => 'o123',
            'nested'       => ['refresh_token' => 'ref-very-secret'],
        ];
        $safe = LogSanitizer::arrayValues($data);
        self::assertStringContainsString('to****et', $safe['access_token']);
        self::assertSame('o123', $safe['openid']);
        self::assertStringContainsString('re****et', $safe['nested']['refresh_token']);
    }

    public function testIsSensitive(): void
    {
        self::assertTrue(LogSanitizer::isSensitive('app_secret'));
        self::assertTrue(LogSanitizer::isSensitive('ACCESS_TOKEN'));
        self::assertFalse(LogSanitizer::isSensitive('openid'));
    }

    public function testMaskFormat(): void
    {
        self::assertSame('ab****56', LogSanitizer::mask('abcdef123456'));
        self::assertSame('', LogSanitizer::mask(''));
        // 长度 <= 4 全掩码
        self::assertSame('****', LogSanitizer::mask('abcd'));
    }
}
