<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core\Crypto;

use Kode\MiniApp\Core\Crypto\RsaPkcs1;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\TestCase;

/**
 * RSA + PKCS#1 v1.5 共享解密工具测试
 *
 * 密钥对由 openssl_pkey_new 现场生成，密文用公钥按与抖音服务端
 * （Go rsa.EncryptPKCS1v15）一致的方式加密，属真实 round-trip 验证。
 */
class RsaPkcs1Test extends TestCase
{
    private string $privateKey = '';
    private string $publicKey  = '';

    protected function setUp(): void
    {
        parent::setUp();

        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($res, '生成测试密钥对失败');

        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);

        $this->privateKey = (string) $privateKey;
        $this->publicKey  = is_array($details) ? (string) $details['key'] : '';
    }

    /**
     * 用公钥模拟服务端加密：超过单块上限时分段，与官方实现一致
     */
    private function encrypt(string $plain, ?string $publicKey = null): string
    {
        $key    = $publicKey ?? $this->publicKey;
        $cipher = '';

        // 2048 位密钥单块明文上限 = 256 - 11 = 245 字节
        foreach (str_split($plain, 245) as $chunk) {
            $block = '';
            self::assertTrue(openssl_public_encrypt($chunk, $block, $key, OPENSSL_PKCS1_PADDING));
            $cipher .= $block;
        }

        return base64_encode($cipher);
    }

    public function testDecryptRoundTrip(): void
    {
        $plain = '{"phoneNumber":"+8613800138000"}';

        self::assertSame($plain, RsaPkcs1::decrypt($this->encrypt($plain), $this->privateKey));
    }

    public function testDecryptJsonReturnsArray(): void
    {
        $payload = [
            'phoneNumber'     => '+8613800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
        ];

        $info = RsaPkcs1::decryptJson($this->encrypt((string) json_encode($payload)), $this->privateKey);

        self::assertSame('13800138000', $info['purePhoneNumber']);
    }

    public function testDecryptHandlesMultipleBlocks(): void
    {
        // 600 字节明文 → 3 个密文块，验证分段解密拼接正确
        $plain = str_repeat('A', 600);

        $cipher = $this->encrypt($plain);
        self::assertSame(768, strlen((string) base64_decode($cipher, true)), '应为 3 个 256 字节密文块');
        self::assertSame($plain, RsaPkcs1::decrypt($cipher, $this->privateKey));
    }

    public function testDecryptAcceptsRawBase64PrivateKey(): void
    {
        $plain = 'hello';

        // 去掉 PEM 头尾与换行，仅保留 Base64 主体
        $raw = (string) preg_replace('/-----[^-]+-----|\s+/', '', $this->privateKey);

        self::assertSame($plain, RsaPkcs1::decrypt($this->encrypt($plain), $raw));
    }

    public function testDecryptRejectsEmptyPrivateKey(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('未配置应用私钥');

        RsaPkcs1::decrypt('Zm9v', '   ');
    }

    public function testDecryptRejectsInvalidPrivateKey(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('应用私钥无效');

        RsaPkcs1::decrypt('Zm9v', 'not-a-real-key');
    }

    public function testDecryptRejectsInvalidBase64(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('不是合法的 base64');

        RsaPkcs1::decrypt('!!!not-base64!!!', $this->privateKey);
    }

    public function testDecryptRejectsMismatchedCipherLength(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('密文长度与密钥长度不匹配');

        RsaPkcs1::decrypt(base64_encode(str_repeat('x', 100)), $this->privateKey);
    }

    public function testDecryptRejectsCipherFromOtherKeyPair(): void
    {
        $other = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($other);
        $details = openssl_pkey_get_details($other);
        self::assertIsArray($details);

        $cipher = $this->encrypt('hello', (string) $details['key']);

        // OpenSSL 3.x 对 PKCS#1 v1.5 启用隐式拒绝（Marvin 攻击缓解）：密钥不匹配时
        // 可能不报错而返回随机明文，故此处只断言「拿不到原始明文」。
        try {
            self::assertNotSame('hello', RsaPkcs1::decrypt($cipher, $this->privateKey));
        } catch (ApiException $e) {
            self::assertStringContainsString('RSA 解密失败', $e->getMessage());
        }
    }

    public function testDecryptJsonRejectsNonJsonPlaintext(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('结果不是合法 JSON');

        RsaPkcs1::decryptJson($this->encrypt('plain text, not json'), $this->privateKey);
    }
}
