<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Alipay;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Utils\Sign;
use PHPUnit\Framework\TestCase;

/**
 * 支付宝客户端敏感数据解密端到端测试
 *
 * 加密向量采用与支付宝官方完全一致算法（AES-128-CBC + 全零 IV + config aes_key）生成，
 * 即真实执行解密链路，而非打桩。验签链路复用 tests/fixtures 中的 RSA 密钥对。
 */
final class DecryptTest extends TestCase
{
    private const string APP_ID = 'alipayapp0000000000';

    private function fixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/' . $name);
        if ($content === false) {
            self::markTestSkipped("缺少 fixtures/{$name}");
        }

        return $content;
    }

    /**
     * 按支付宝官方算法（AES-128-CBC + 全零 IV）加密明文，返回 base64 密文
     *
     * @param array<string, mixed> $payload
     */
    private function encrypt(array $payload, string $aesKeyRaw): string
    {
        $plain = json_encode($payload);
        \assert(is_string($plain));
        $iv     = str_repeat("\0", 16);
        $cipher = openssl_encrypt($plain, 'aes-128-cbc', $aesKeyRaw, OPENSSL_RAW_DATA, $iv);
        \assert(is_string($cipher));

        return base64_encode($cipher);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function makeApp(array $config = []): AlipayApp
    {
        $app = (new Kernel([
            'alipay' => array_merge([
                'app_id' => self::APP_ID,
                'aes_key' => base64_encode(random_bytes(16)),
            ], $config),
        ]))->alipay()->app();
        \assert($app instanceof AlipayApp);

        return $app;
    }

    public function testDecryptPhoneRoundTrip(): void
    {
        $aesKey = random_bytes(16);
        $app    = $this->makeApp(['aes_key' => base64_encode($aesKey)]);

        $payload  = ['mobile' => '13800138000', 'countryCode' => '86'];
        $response = $this->encrypt($payload, $aesKey);

        $result = $app->decrypt()->phone($response);

        self::assertSame('13800138000', $result['mobile']);
        self::assertSame('86', $result['countryCode']);
    }

    public function testDecryptDataReturnsRawArray(): void
    {
        $aesKey  = random_bytes(16);
        $app     = $this->makeApp(['aes_key' => base64_encode($aesKey)]);
        $payload = ['mobile' => '13800138000', 'foo' => 'bar'];

        $response = $this->encrypt($payload, $aesKey);
        $data     = $app->decrypt()->data($response);

        self::assertSame($payload, $data);
    }

    public function testMissingMobileFieldThrows(): void
    {
        $aesKey  = random_bytes(16);
        $app     = $this->makeApp(['aes_key' => base64_encode($aesKey)]);
        $response = $this->encrypt(['foo' => 'bar'], $aesKey);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('mobile');
        $app->decrypt()->phone($response);
    }

    public function testWrongAesKeyThrows(): void
    {
        $app     = $this->makeApp(['aes_key' => base64_encode(random_bytes(16))]);
        $response = $this->encrypt(['mobile' => '13800138000'], random_bytes(16));

        $this->expectException(ApiException::class);
        $app->decrypt()->data($response);
    }

    public function testInvalidAesKeyConfigThrows(): void
    {
        $app = $this->makeApp(['aes_key' => base64_encode(random_bytes(8))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('aes_key');
        $app->decrypt()->data(base64_encode('x'));
    }

    public function testInvalidBase64Throws(): void
    {
        $app = $this->makeApp();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('base64');
        $app->decrypt()->data('!!!not-base64!!!');
    }

    public function testSignVerifyRoundTrip(): void
    {
        $privateKey = $this->fixture('rsa_private.pem');
        $publicKey  = $this->fixture('rsa_public.pem');

        $aesKey  = random_bytes(16);
        $app     = $this->makeApp(['aes_key' => base64_encode($aesKey), 'public_key' => $publicKey]);
        $response = $this->encrypt(['mobile' => '13800138000'], $aesKey);
        $sign    = Sign::rsaRaw($response, $privateKey, 'sha256');

        self::assertTrue($app->decrypt()->verifySign($response, $sign));
        self::assertFalse($app->decrypt()->verifySign($response, 'invalid-sign'));

        $result = $app->decrypt()->phone($response, $sign);
        self::assertSame('13800138000', $result['mobile']);
    }

    public function testPhoneWithBadSignThrows(): void
    {
        $privateKey = $this->fixture('rsa_private.pem');
        $publicKey  = $this->fixture('rsa_public.pem');

        $aesKey   = random_bytes(16);
        $app      = $this->makeApp(['aes_key' => base64_encode($aesKey), 'public_key' => $publicKey]);
        $response = $this->encrypt(['mobile' => '13800138000'], $aesKey);
        $badSign  = Sign::rsaRaw('tampered-response', $privateKey, 'sha256');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('验签');
        $app->decrypt()->phone($response, $badSign);
    }

    public function testVerifySignWithoutPublicKeyThrows(): void
    {
        $app = $this->makeApp();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('公钥');
        $app->decrypt()->verifySign('x', 'y');
    }
}
