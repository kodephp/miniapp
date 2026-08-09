<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use InvalidArgumentException;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;

/**
 * Union 层统一「encryptedData 解密获取手机号」入口测试
 *
 * 覆盖 phoneByDecrypt（显式 session_key）与 phoneByUser（缓存 session_key 一站式），
 * 验证返回结构经 PhoneNormalizer 归一化且原字段保留；支付宝不在覆盖范围内，应抛错。
 */
class PhoneByDecryptTest extends TestCase
{
    private const APP_ID = 'wxapp0000000000';

    private function makeUnion(): Union
    {
        return (new Kernel([
            'wechat' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
            'douyin' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
            'baidu' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
            'lark' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
            'qq' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
            'wechat_work' => [
                'corp_id'  => self::APP_ID,
                'app_id'   => self::APP_ID,
                'secret'   => 'app-secret',
                'agent_id' => '1000002',
            ],
            'alipay' => [
                'app_id'  => self::APP_ID,
                'aes_key' => base64_encode(random_bytes(16)),
            ],
        ]))->union();
    }

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

    /**
     * @param array<string, mixed> $payload
     * @return array{0:string,1:string,2:string}
     */
    private function encryptLark(array $payload): array
    {
        $sessionKey = bin2hex(random_bytes(16));
        $iv         = bin2hex(random_bytes(16));
        $key        = hex2bin($sessionKey);
        $vec        = hex2bin($iv);
        \assert(is_string($key) && is_string($vec));

        $plain  = json_encode($payload);
        \assert(is_string($plain));
        $cipher = openssl_encrypt($plain, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $vec);
        \assert(is_string($cipher));

        return [base64_encode($cipher), $sessionKey, $iv];
    }

    public function testPhoneByDecryptWechatReturnsNormalized(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $info = $union->phoneByDecrypt(Channel::WechatMini, $encrypted, $sessionKey, $iv);

        self::assertSame('13800138000', $info['phoneNumber']);
        self::assertSame('13800138000', $info['purePhoneNumber']);
        self::assertSame('86', $info['countryCode']);
        // 原字段保留
        self::assertSame(self::APP_ID, $info['watermark']['appid']);
    }

    public function testPhoneByDecryptLarkHexEncoding(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'phoneNumber' => '13800138000',
            'countryCode' => '86',
        ];

        [$encrypted, $sessionKey, $iv] = $this->encryptLark($payload);
        $info = $union->phoneByDecrypt(Channel::Lark, $encrypted, $sessionKey, $iv);

        self::assertSame('13800138000', $info['phoneNumber']);
        self::assertSame('86', $info['countryCode']);
        // Lark 明文无 purePhoneNumber / watermark，归一化兜底推导
        self::assertSame('13800138000', $info['purePhoneNumber']);
    }

    public function testPhoneByDecryptWechatWorkUsesAppIdWatermark(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $info = $union->phoneByDecrypt(Channel::WechatWork, $encrypted, $sessionKey, $iv);

        self::assertSame('13800138000', $info['phoneNumber']);
        self::assertSame('86', $info['countryCode']);
    }

    public function testPhoneByUserWechatCachedSessionKey(): void
    {
        $kernel = new \Kode\MiniApp\Kernel([
            'wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret'],
        ]);
        $union = $kernel->union();
        $wxApp = $kernel->wechat()->app();
        \assert($wxApp instanceof WechatApp);

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        SessionKeyManager::for($wxApp->config())->store('openid-wx', $sessionKey);

        $info = $union->phoneByUser(Channel::WechatMini, $encrypted, $iv, 'openid-wx');

        self::assertSame('13800138000', $info['phoneNumber']);
        self::assertSame('86', $info['countryCode']);
    }

    public function testPhoneByDecryptAlipayThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 encryptedData 解密获取手机号');
        $union->phoneByDecrypt(Channel::AlipayMini, 'enc', 'sk', 'iv');
    }

    public function testPhoneByUserAlipayThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 encryptedData 解密获取手机号');
        $union->phoneByUser(Channel::AlipayMini, 'enc', 'iv', 'openid');
    }

    public function testPhoneByDecryptDouyin(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $info = $union->phoneByDecrypt(Channel::DouyinMini, $encrypted, $sessionKey, $iv);

        self::assertSame('13800138000', $info['phoneNumber']);
    }
}
