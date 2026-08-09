<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Lark;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Lark\LarkApp;
use Kode\MiniApp\Tests\TestCase;

/**
 * 飞书小程序客户端敏感数据解密测试
 *
 * 飞书与微信同族 AES-128-CBC + PKCS#7，但 session_key 与 iv 采用 hex 编码、
 * 明文不含 watermark。本测试按该真实参数生成加密向量（hex key/iv + base64 密文），
 * 即是对「真实对接」能力的端到端验证。
 */
class DecryptTest extends TestCase
{
    private const APP_ID = 'cli_abc123';

    private function makeApp(): LarkApp
    {
        $app = (new Kernel([
            'lark' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
        ]))->lark()->app();
        \assert($app instanceof LarkApp);

        return $app;
    }

    /**
     * 带缓存的 App（用于一站式解密测试）
     *
     * @return array{0: LarkApp, 1: ArrayCache}
     */
    private function makeAppWithCache(): array
    {
        $cache = new ArrayCache();
        $app = (new Kernel([
            'lark' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
                'cache'      => $cache,
            ],
        ]))->lark()->app();
        \assert($app instanceof LarkApp);

        return [$app, $cache];
    }

    /**
     * 按飞书官方算法生成一段 encryptedData，返回 [encryptedData, sessionKey(hex), iv(hex)]
     *
     * 飞书：key = hex2bin(sessionKey)，iv = hex2bin(iv)，密文 = base64(aes_128_cbc(key, iv, plain))
     *
     * @param array<string, mixed> $payload
     * @return array{0:string,1:string,2:string}
     */
    private function encrypt(array $payload): array
    {
        $sessionKey = bin2hex(random_bytes(16)); // hex 编码的 16 字节
        $iv         = bin2hex(random_bytes(16)); // hex 编码的 16 字节
        $key        = hex2bin($sessionKey);
        $vec        = hex2bin($iv);
        \assert(is_string($key) && is_string($vec));

        $plain  = json_encode($payload);
        \assert(is_string($plain));
        $cipher = openssl_encrypt($plain, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $vec);
        \assert(is_string($cipher));

        return [base64_encode($cipher), $sessionKey, $iv];
    }

    public function testDecryptPhone(): void
    {
        $app = $this->makeApp();

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $phone = $app->decrypt()->phone($encrypted, $sessionKey, $iv);

        self::assertSame('13800138000', $phone['phoneNumber']);
        self::assertSame('86', $phone['countryCode']);
    }

    public function testDecryptUserProfileRoundTrip(): void
    {
        $app = $this->makeApp();

        $payload = [
            'name'   => '张三',
            'openId' => self::APP_ID,
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $data = $app->decrypt()->userInfo($encrypted, $sessionKey, $iv);

        self::assertSame($payload, $data);
    }

    public function testWrongSessionKeyThrows(): void
    {
        $app = $this->makeApp();

        $payload = ['phoneNumber' => '13800138000'];
        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $wrongSessionKey = bin2hex(random_bytes(16));

        $this->expectException(ApiException::class);
        $app->decrypt()->data($encrypted, $wrongSessionKey, $iv);
    }

    public function testInvalidHexThrows(): void
    {
        $app = $this->makeApp();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('hex 解析错误');
        // 非 hex 字符的 session_key
        $app->decrypt()->data(base64_encode(random_bytes(32)), '!!!not-hex!!!', bin2hex(random_bytes(16)));
    }

    public function testMalformedKeyLengthThrows(): void
    {
        $app = $this->makeApp();

        // 14 hex 字符（非 32，即非 16 字节）应被拒绝
        $badKey = bin2hex(random_bytes(7));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('密钥或向量长度非法');
        $app->decrypt()->data(base64_encode(random_bytes(32)), $badKey, bin2hex(random_bytes(16)));
    }

    public function testDecryptByUserRoundTrip(): void
    {
        [$app, $cache] = $this->makeAppWithCache();

        $payload = ['name' => '张三', 'openId' => self::APP_ID];
        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);

        SessionKeyManager::for($app->config())->store('user-openid', $sessionKey);
        self::assertTrue($cache->has(SessionKeyManager::for($app->config())->key('user-openid')));

        $data = $app->decrypt()->dataByUser($encrypted, $iv, 'user-openid');
        self::assertSame($payload, $data);
    }

    public function testPhoneByUserRoundTrip(): void
    {
        [$app] = $this->makeAppWithCache();

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
        ];
        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        SessionKeyManager::for($app->config())->store('user-openid', $sessionKey);

        $phone = $app->decrypt()->phoneByUser($encrypted, $iv, 'user-openid');
        self::assertSame('13800138000', $phone['phoneNumber']);
    }

    public function testDecryptByUserMissingCacheThrows(): void
    {
        [$app] = $this->makeAppWithCache();

        [$encrypted] = $this->encrypt(['name' => '张三']);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('未找到用户');
        $app->decrypt()->dataByUser($encrypted, bin2hex(random_bytes(16)), 'unknown-openid');
    }
}
