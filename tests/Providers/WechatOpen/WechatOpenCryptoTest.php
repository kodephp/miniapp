<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatOpen;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Tests\TestCase;

/**
 * 微信开放平台 Crypto 加解密测试
 */
class WechatOpenCryptoTest extends TestCase
{
    private static function aesKey(): string
    {
        // 43 位 base64 字符串
        return strtr(base64_encode(random_bytes(32)), ['=' => '']);
    }

    private static function makeKernel(): Kernel
    {
        return new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => self::aesKey(),
            ],
        ]);
    }

    public function testEncryptAndDecryptRoundTrip(): void
    {
        /** @var WechatOpenApp $app */
        $app     = self::makeKernel()->wechatOpen()->app();
        $crypto  = $app->crypto();

        $message = '{"ToUserName":"gh_abc","Content":"hello"}';
        $time    = '1700000000';
        $nonce   = 'n123456';

        $payload = $crypto->encryptMessage($message, $time, $nonce);
        $body    = json_decode($payload, true);
        self::assertIsArray($body);
        self::assertSame($time, $body['TimeStamp']);
        self::assertSame($nonce, $body['Nonce']);
        self::assertNotEmpty($body['Encrypt']);
        self::assertNotEmpty($body['MsgSignature']);

        $decrypted = $crypto->decryptMessage(
            $body['Encrypt'],
            $body['MsgSignature'],
            $time,
            $nonce,
        );

        self::assertSame($message, $decrypted);
    }

    public function testDecryptRejectsBadSignature(): void
    {
        /** @var WechatOpenApp $app */
        $app     = self::makeKernel()->wechatOpen()->app();
        $crypto  = $app->crypto();

        $message = 'hello world';
        $time    = '1700000000';
        $nonce   = 'n123456';

        $payload = json_decode($crypto->encryptMessage($message, $time, $nonce), true);

        $this->expectException(\RuntimeException::class);
        $crypto->decryptMessage($payload['Encrypt'], 'wrong-sig', $time, $nonce);
    }

    public function testInvalidAesKeyRejected(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => 'too-short',
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();
        $crypto = $app->crypto();

        $this->expectException(\RuntimeException::class);
        $crypto->encryptMessage('hello', '1700000000', 'n1');
    }
}
