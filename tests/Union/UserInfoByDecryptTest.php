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
 * Union 层统一「encryptedData 解密获取用户资料」入口测试
 *
 * 覆盖 userInfoByDecrypt（显式 session_key）与 userInfoByUser（缓存 session_key 一站式），
 * 验证返回各端用户资料数组（原始字段保留 + 归一化 canonical 键）；支付宝不在覆盖范围内，应抛错。
 */
class UserInfoByDecryptTest extends TestCase
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

    public function testUserInfoByDecryptWechatReturnsProfile(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'nickName'  => 'TestUser',
            'avatarUrl' => 'https://example.com/a.png',
            'gender'    => 1,
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $info = $union->userInfoByDecrypt(Channel::WechatMini, $encrypted, $sessionKey, $iv);

        self::assertSame('TestUser', $info['nickName']);
        self::assertSame('https://example.com/a.png', $info['avatarUrl']);
        self::assertSame(self::APP_ID, $info['watermark']['appid']);

        // 归一化：原始字段保留，并追加 snake_case canonical 键（与 UnionUser 命名对齐）
        self::assertSame('TestUser', $info['nickname']);
        self::assertSame('https://example.com/a.png', $info['avatar']);
        self::assertSame(1, $info['gender']);
        self::assertSame(self::APP_ID, $info['watermark']['appid']);
    }

    public function testUserInfoByDecryptLarkHexEncoding(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'nickName'  => 'LarkUser',
            'avatarUrl' => 'https://example.com/l.png',
        ];

        [$encrypted, $sessionKey, $iv] = $this->encryptLark($payload);
        $info = $union->userInfoByDecrypt(Channel::Lark, $encrypted, $sessionKey, $iv);

        self::assertSame('LarkUser', $info['nickName']);
    }

    public function testUserInfoByUserWechatCachedSessionKey(): void
    {
        $kernel = new Kernel([
            'wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret'],
        ]);
        $union = $kernel->union();
        $wxApp = $kernel->wechat()->app();
        \assert($wxApp instanceof WechatApp);

        $payload = [
            'nickName'  => 'CachedUser',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        SessionKeyManager::for($wxApp->config())->store('openid-wx', $sessionKey);

        $info = $union->userInfoByUser(Channel::WechatMini, $encrypted, $iv, 'openid-wx');

        self::assertSame('CachedUser', $info['nickName']);
    }

    public function testUserInfoByDecryptAlipayThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 encryptedData 解密（手机号 / 用户资料）');
        $union->userInfoByDecrypt(Channel::AlipayMini, 'enc', 'sk', 'iv');
    }

    public function testUserInfoByUserAlipayThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 encryptedData 解密（手机号 / 用户资料）');
        $union->userInfoByUser(Channel::AlipayMini, 'enc', 'iv', 'openid');
    }

    public function testUserInfoByDecryptDouyin(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'nickName'  => 'DouyinUser',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $info = $union->userInfoByDecrypt(Channel::DouyinMini, $encrypted, $sessionKey, $iv);

        self::assertSame('DouyinUser', $info['nickName']);
    }
}
