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
use Kode\MiniApp\Union\UnionUser;

/**
 * Union 层「收敛为 UnionUser 对象」的用户资料入口测试
 *
 * 覆盖 userInfoObjectByDecrypt（显式 session_key）与 userInfoObjectByUser（缓存 session_key），
 * 验证返回 UnionUser 对象、字段正确归一化、不支持渠道抛错。
 */
class UserInfoObjectByDecryptTest extends TestCase
{
    private const APP_ID = 'wxapp0000000000';

    private function makeUnion(): Union
    {
        return (new Kernel([
            'wechat'     => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret'],
            'alipay'     => ['app_id' => self::APP_ID, 'aes_key' => base64_encode(random_bytes(16))],
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

    public function testUserInfoObjectByDecryptReturnsUnionUser(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'nickName'  => 'ObjectUser',
            'avatarUrl' => 'https://example.com/o.png',
            'gender'    => 2,
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $user = $union->userInfoObjectByDecrypt(
            Channel::WechatMini,
            $encrypted,
            $sessionKey,
            $iv,
            'openid-o',
            'union-o',
        );

        self::assertInstanceOf(UnionUser::class, $user);
        self::assertSame('openid-o', $user->openId);
        self::assertSame('union-o', $user->unionId);
        self::assertSame(Channel::WechatMini, $user->channel);
        self::assertSame('ObjectUser', $user->nickname);
        self::assertSame('https://example.com/o.png', $user->avatar);
        self::assertSame('2', $user->gender);
    }

    public function testUserInfoObjectByUserReturnsUnionUser(): void
    {
        $kernel = new Kernel([
            'wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret'],
        ]);
        $union = $kernel->union();
        $wxApp = $kernel->wechat()->app();
        \assert($wxApp instanceof WechatApp);

        $payload = [
            'nickName'  => 'CachedObj',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        SessionKeyManager::for($wxApp->config())->store('openid-co', $sessionKey);

        $user = $union->userInfoObjectByUser(Channel::WechatMini, $encrypted, $iv, 'openid-co');

        self::assertInstanceOf(UnionUser::class, $user);
        self::assertSame('openid-co', $user->openId);
        self::assertSame('CachedObj', $user->nickname);
    }

    public function testUserInfoObjectByDecryptAlipayThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 encryptedData 解密（手机号 / 用户资料）');
        $union->userInfoObjectByDecrypt(Channel::AlipayMini, 'enc', 'sk', 'iv');
    }
}
