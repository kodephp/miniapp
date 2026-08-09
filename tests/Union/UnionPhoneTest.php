<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\UnionPhone;

/**
 * UnionPhone 值对象工厂测试
 */
class UnionPhoneTest extends TestCase
{
    public function testFromArrayBuildsObject(): void
    {
        $phone = UnionPhone::fromArray([
            'phoneNumber'     => '+86 13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'mobile'          => '13800138000',
        ]);

        self::assertInstanceOf(UnionPhone::class, $phone);
        self::assertSame('+86 13800138000', $phone->phoneNumber);
        self::assertSame('13800138000', $phone->purePhoneNumber);
        self::assertSame('86', $phone->countryCode);
        // 原始字段保留
        self::assertSame('13800138000', $phone->raw['mobile']);
    }

    public function testMissingFieldsNormalizedToEmptyString(): void
    {
        $phone = UnionPhone::fromArray([]);

        self::assertSame('', $phone->phoneNumber);
        self::assertSame('', $phone->purePhoneNumber);
        self::assertSame('', $phone->countryCode);
        self::assertSame([], $phone->raw);
    }

    public function testToArray(): void
    {
        $phone = UnionPhone::fromArray([
            'phoneNumber'     => '+86 13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
        ]);

        self::assertSame(
            ['phoneNumber' => '+86 13800138000', 'purePhoneNumber' => '13800138000', 'countryCode' => '86'],
            $phone->toArray(),
        );
    }

    public function testJsonSerializeEqualsToArray(): void
    {
        $phone = UnionPhone::fromArray([
            'phoneNumber'     => '+86 13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'mobile'          => '13800138000',
        ]);

        // 直接 json_encode 可用，且结果等价于 toArray（仅核心三元组，不含 raw）
        $json = json_encode($phone);
        self::assertIsString($json);
        self::assertSame(
            ['phoneNumber' => '+86 13800138000', 'purePhoneNumber' => '13800138000', 'countryCode' => '86'],
            json_decode($json, true),
        );
    }
}
