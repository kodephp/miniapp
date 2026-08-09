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
use Kode\MiniApp\Union\UnionPhone;
use Kode\MiniApp\Union\UnionUser;

/**
 * UnionUser 桥接解密入口测试
 *
 * 验证「登录拿到 UnionUser 之后，用它一键解密手机号 / 用户资料」的便捷链路：
 * phoneObjectForUser / userInfoObjectForUser 从 UnionUser 取回 channel + openId，
 * 复用 *ByUser 路径（session_key 已由登录阶段托管），无需重复传参。
 */
class UnionUserBridgeTest extends TestCase
{
    private const APP_ID = 'wxapp0000000000';

    private function makeUnion(): Union
    {
        return (new Kernel([
            'wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret'],
        ]))->union();
    }

    /**
     * 微信 AES-128-CBC 加密，返回 [密文, sessionKey, iv]
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

    public function testPhoneObjectForUserReturnsUnionPhone(): void
    {
        $kernel = new Kernel(['wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret']]);
        $union  = $kernel->union();
        $wxApp  = $kernel->wechat()->app();
        \assert($wxApp instanceof WechatApp);

        // 模拟「登录后拿到的 UnionUser」
        $user = new UnionUser(unionId: '', openId: 'openid-bridge', channel: Channel::WechatMini);

        $payload = [
            'phoneNumber'     => '13900139000',
            'purePhoneNumber' => '13900139000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];
        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        // 登录阶段已托管 session_key（按 openId 维度）
        SessionKeyManager::for($wxApp->config())->store('openid-bridge', $sessionKey);

        $phone = $union->phoneObjectForUser($user, $encrypted, $iv);

        self::assertInstanceOf(UnionPhone::class, $phone);
        self::assertSame('13900139000', $phone->phoneNumber);
        self::assertSame('86', $phone->countryCode);
    }

    public function testUserInfoObjectForUserReturnsUnionUser(): void
    {
        $kernel = new Kernel(['wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret']]);
        $union  = $kernel->union();
        $wxApp  = $kernel->wechat()->app();
        \assert($wxApp instanceof WechatApp);

        // 模拟「登录后拿到的 UnionUser」（已携带开放平台 unionId）
        $user = new UnionUser(unionId: 'union-bridge', openId: 'openid-bridge', channel: Channel::WechatMini);

        $payload = [
            'nickName'  => 'BridgeUser',
            'avatarUrl' => 'https://example.com/b.png',
            'gender'    => 1,
            'city'      => 'Shenzhen',
            'province'  => 'Guangdong',
            'country'   => 'China',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];
        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        SessionKeyManager::for($wxApp->config())->store('openid-bridge', $sessionKey);

        $decrypted = $union->userInfoObjectForUser($user, $encrypted, $iv);

        self::assertInstanceOf(UnionUser::class, $decrypted);
        self::assertSame('BridgeUser', $decrypted->nickname);
        self::assertSame('openid-bridge', $decrypted->openId);
        // unionId 从登录 UnionUser 透传，不取自加密明文
        self::assertSame('union-bridge', $decrypted->unionId);
    }

    public function testPhoneObjectForUserUnsupportedChannelThrows(): void
    {
        $union = $this->makeUnion();
        $user  = new UnionUser(unionId: '', openId: 'o1', channel: Channel::AlipayMini);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 encryptedData 解密（手机号 / 用户资料）');
        $union->phoneObjectForUser($user, 'enc', 'iv');
    }
}
