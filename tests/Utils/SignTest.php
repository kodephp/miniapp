<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Utils;

use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Utils\Sign;

/**
 * Sign 工具类测试
 */
class SignTest extends TestCase
{
    public function testMd5(): void
    {
        $params = ['a' => '1', 'b' => '2'];
        $sign   = Sign::md5($params, 'key');

        self::assertSame(32, strlen($sign));
        self::assertSame(strtoupper(md5('a=1&b=2&key=key')), $sign);
    }

    public function testHmac(): void
    {
        $params = ['a' => '1'];
        $sign   = Sign::hmac($params, 'key');

        self::assertSame(64, strlen($sign));
    }

    public function testRsaAndVerify(): void
    {
        $privateKey = file_get_contents(__DIR__ . '/../fixtures/rsa_private.pem');
        $publicKey  = file_get_contents(__DIR__ . '/../fixtures/rsa_public.pem');

        if ($privateKey === false || $publicKey === false) {
            self::markTestSkipped('RSA 密钥文件不存在');
        }

        $params = ['foo' => 'bar'];
        $sign   = Sign::rsa($params, $privateKey);

        self::assertTrue(Sign::verifyRsa($params, $publicKey, $sign));
        self::assertFalse(Sign::verifyRsa($params, $publicKey, 'invalid'));
    }
}
