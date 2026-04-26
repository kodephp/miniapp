<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Utils;

use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Utils\Str;

/**
 * Str 工具类测试
 */
class StrTest extends TestCase
{
    public function testRandom(): void
    {
        $str = Str::random(16);

        self::assertSame(16, strlen($str));
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $str);
    }

    public function testUuid(): void
    {
        $uuid = Str::uuid();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    public function testCamel(): void
    {
        self::assertSame('fooBar', Str::camel('foo_bar'));
        self::assertSame('fooBarBaz', Str::camel('foo_bar_baz'));
    }

    public function testSnake(): void
    {
        self::assertSame('foo_bar', Str::snake('fooBar'));
        self::assertSame('foo_bar_baz', Str::snake('fooBarBaz'));
    }
}
