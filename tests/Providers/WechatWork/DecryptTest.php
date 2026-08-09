<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatWork;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;
use Kode\MiniApp\Tests\TestCase;

/**
 * 企业微信小程序客户端敏感数据解密测试
 *
 * 算法与微信完全一致（AES-128-CBC + PKCS#7）。企业微信明文 watermark.appid 实为
 * 「小程序 appId」（而非企业 corpid），故校验须用 app_id。加密向量按官方一致算法生成，即端到端验证。
 */
class DecryptTest extends TestCase
{
    private const CORP_ID = 'corp123';
    private const APP_ID  = 'wwapp123';

    private function makeApp(): WechatWorkApp
    {
        $app = (new Kernel([
            'wechat_work' => [
                'corp_id'  => self::CORP_ID,
                'app_id'   => self::APP_ID,
                'secret'   => 'app-secret',
                'agent_id' => '1000002',
            ],
        ]))->wechatWork()->app();
        \assert($app instanceof WechatWorkApp);

        return $app;
    }

    /**
     * 带缓存的 App（用于一站式解密测试）
     *
     * @return array{0: WechatWorkApp, 1: ArrayCache}
     */
    private function makeAppWithCache(): array
    {
        $cache = new ArrayCache();
        $app = (new Kernel([
            'wechat_work' => [
                'corp_id'  => self::CORP_ID,
                'app_id'   => self::APP_ID,
                'secret'   => 'app-secret',
                'agent_id' => '1000002',
                'cache'    => $cache,
            ],
        ]))->wechatWork()->app();
        \assert($app instanceof WechatWorkApp);

        return [$app, $cache];
    }

    /**
     * 按企业微信（=微信）官方算法生成一段 encryptedData，返回 [encryptedData, sessionKey, iv]
     *
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

    public function testDecryptUserProfileRoundTrip(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'   => 'Band',
            'gender'     => 1,
            'language'   => 'zh_CN',
            'city'       => 'Guangzhou',
            'province'   => 'Guangdong',
            'country'    => 'CN',
            'avatarUrl'  => 'https://example.com/a.png',
            'watermark'  => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
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
        $phone     = $app->decrypt()->phone($encrypted, $sessionKey, $iv);

        self::assertSame('13800138000', $phone['phoneNumber']);
        self::assertSame('86', $phone['countryCode']);
    }

    public function testWatermarkCorpIdMismatchThrows(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => 'evil-corp', 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('watermark.appid 校验不通过');
        $app->decrypt()->data($encrypted, $sessionKey, $iv);
    }

    /**
     * 企业微信官方明确 watermark.appid 应为小程序 appId，而非企业 corpid。
     * 故用 corpid 充当 watermark 时，解密必须失败（修复 v1.27.0 误用 corpid 校验的问题）。
     */
    public function testWatermarkCorpIdNoLongerAccepted(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => self::CORP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('watermark.appid 校验不通过');
        $app->decrypt()->data($encrypted, $sessionKey, $iv);
    }

    /**
     * 开启 watermark 校验但未配置 app_id 时，应给出清晰可操作的错误，而非误判。
     */
    public function testMissingAppIdConfigThrows(): void
    {
        $app = (new Kernel([
            'wechat_work' => [
                'corp_id'  => self::CORP_ID,
                'secret'   => 'app-secret',
                'agent_id' => '1000002',
            ],
        ]))->wechatWork()->app();
        \assert($app instanceof WechatWorkApp);

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('app_id 未配置');
        $app->decrypt()->data($encrypted, $sessionKey, $iv);
    }

    public function testVerifyAppIdCanBeDisabled(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => 'evil-corp', 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $data      = $app->decrypt()->data($encrypted, $sessionKey, $iv, false);

        self::assertSame('Band', $data['nickName']);
    }

    public function testWrongSessionKeyThrows(): void
    {
        $app = $this->makeApp();

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $wrongSessionKey  = base64_encode(random_bytes(16));

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

        // 12 字节密钥（非 16 字节）应被拒绝
        $badKey = base64_encode(random_bytes(12));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('密钥或向量长度非法');
        $app->decrypt()->data(base64_encode(random_bytes(32)), $badKey, base64_encode(random_bytes(16)));
    }

    public function testDecryptByUserRoundTrip(): void
    {
        [$app, $cache] = $this->makeAppWithCache();

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);

        // 模拟「登录阶段已托管 session_key」
        SessionKeyManager::for($app->config())->store('user-openid', $sessionKey);
        self::assertTrue($cache->has(SessionKeyManager::for($app->config())->key('user-openid')));

        $decrypted = $app->decrypt()->dataByUser($encrypted, $iv, 'user-openid');
        self::assertSame($payload, $decrypted);
    }

    public function testPhoneByUserRoundTrip(): void
    {
        [$app] = $this->makeAppWithCache();

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);

        SessionKeyManager::for($app->config())->store('user-openid', $sessionKey);

        $phone = $app->decrypt()->phoneByUser($encrypted, $iv, 'user-openid');
        self::assertSame('13800138000', $phone['phoneNumber']);
        self::assertSame('86', $phone['countryCode']);
    }

    public function testByUserMissingCacheThrows(): void
    {
        [$app] = $this->makeAppWithCache();

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted] = $this->encrypt($payload);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('未找到用户');
        $app->decrypt()->dataByUser($encrypted, base64_encode(random_bytes(16)), 'unknown');
    }
}
