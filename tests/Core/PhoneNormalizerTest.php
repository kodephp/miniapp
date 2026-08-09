<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Core\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * 手机号输出归一化测试
 */
final class PhoneNormalizerTest extends TestCase
{
    public function testNormalizesMobileToPhoneNumber(): void
    {
        $result = PhoneNormalizer::normalize(['mobile' => '13800138000', 'countryCode' => '86']);

        self::assertSame('13800138000', $result['phoneNumber']);
        self::assertSame('13800138000', $result['purePhoneNumber']);
        self::assertSame('86', $result['countryCode']);
    }

    public function testKeepsWechatStyleKeys(): void
    {
        $raw    = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => 'wx1'],
        ];
        $result = PhoneNormalizer::normalize($raw);

        self::assertSame('13800138000', $result['phoneNumber']);
        self::assertSame('86', $result['countryCode']);
    }

    public function testDerivesPurePhoneNumberFromCountryCode(): void
    {
        $result = PhoneNormalizer::normalize(['phoneNumber' => '+8613800138000', 'countryCode' => '86']);

        self::assertSame('+8613800138000', $result['phoneNumber']);
        self::assertSame('13800138000', $result['purePhoneNumber']);
    }

    public function testMissingFieldsYieldEmptyStrings(): void
    {
        $result = PhoneNormalizer::normalize([]);

        self::assertSame('', $result['phoneNumber']);
        self::assertSame('', $result['purePhoneNumber']);
        self::assertSame('', $result['countryCode']);
    }

    public function testIgnoresNonStringValues(): void
    {
        $result = PhoneNormalizer::normalize(['mobile' => null, 'countryCode' => 86]);

        self::assertSame('', $result['phoneNumber']);
        self::assertSame('', $result['countryCode']);
    }
}
