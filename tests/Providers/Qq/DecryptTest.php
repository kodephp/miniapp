<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Qq;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Qq\QqApp;
use PHPUnit\Framework\TestCase;

/**
 * QQ 客户端敏感数据解密端到端测试
 *
 * 加密向量采用与微信/QQ 官方完全一致算法（AES-128-CBC + PKCS#7）生成，
 * 即真实执行解密链路，而非打桩。
 */
final class DecryptTest extends TestCase
{
    private const string APP_ID = 'qqapp000000000000';

    /**
     * @param array<string, mixed> $payload
     * @return array{0:string,1:string,2:string}
     */
    private function encrypt(array $payload): array
    {
        $sessionKey = base64_encode(random_bytes(16));
        $iv         = base64_encode(random_bytes(16));
        $key        = base64_decode($sessionKey, true);
        $vec        = base64_decode($iv, true);
        \assert(is_string($key) && is_string($vec));

        $plain  = json_encode($payload);
        \assert(is_string($plain));
        $cipher = openssl_encrypt($plain, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $vec);
        \assert(is_string($cipher));

        return [base64_encode($cipher), $sessionKey, $iv];
    }

    private function makeApp(): QqApp
    {
        $app = (new Kernel([
            'qq' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
        ]))->qq()->app();
        \assert($app instanceof QqApp);

        return $app;
    }

    public function testDecryptUserProfileRoundTrip(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'  => 'QqUser',
            'gender'    => 1,
            'avatarUrl' => 'https://example.com/a.png',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $decrypted = $app->decrypt()->userInfo($encrypted, $sessionKey, $iv);

        self::assertSame($payload, $decrypted);
    }

    public function testDecryptPhone(): void
    {
        $app = $this->makeApp();

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $phone = $app->decrypt()->phone($encrypted, $sessionKey, $iv);

        self::assertSame('13800138000', $phone['phoneNumber']);
        self::assertSame('86', $phone['countryCode']);
    }

    public function testWatermarkAppIdMismatchThrows(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'  => 'QqUser',
            'watermark' => ['appid' => 'evil-appid', 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('watermark.appid 校验不通过');
        $app->decrypt()->data($encrypted, $sessionKey, $iv);
    }

    public function testVerifyAppIdCanBeDisabled(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'  => 'QqUser',
            'watermark' => ['appid' => 'evil-appid', 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $data = $app->decrypt()->data($encrypted, $sessionKey, $iv, false);

        self::assertSame('QqUser', $data['nickName']);
    }

    public function testWrongSessionKeyThrows(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'  => 'QqUser',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $wrongSessionKey = base64_encode(random_bytes(16));

        $this->expectException(ApiException::class);
        $app->decrypt()->data($encrypted, $wrongSessionKey, $iv);
    }

    public function testInvalidBase64Throws(): void
    {
        $app = $this->makeApp();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('base64 解析错误');
        $app->decrypt()->data('!!!not-base64!!!', base64_encode(random_bytes(16)), base64_encode(random_bytes(16)));
    }

    public function testMalformedKeyLengthThrows(): void
    {
        $app = $this->makeApp();

        $badKey = base64_encode(random_bytes(12));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('密钥或向量长度非法');
        $app->decrypt()->data(base64_encode(random_bytes(32)), $badKey, base64_encode(random_bytes(16)));
    }
}
