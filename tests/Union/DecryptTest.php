<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use InvalidArgumentException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;

/**
 * Union 层客户端敏感数据解密分派测试（微信 / 抖音 / QQ）
 */
class DecryptTest extends TestCase
{
    private const APP_ID = 'wxapp0000000000';

    private function makeUnion(): Union
    {
        return (new \Kode\MiniApp\Kernel([
            'wechat' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
            'douyin' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
            'qq' => [
                'app_id'     => self::APP_ID,
                'app_secret' => 'app-secret',
            ],
        ]))->union();
    }

    /**
     * 按微信官方算法生成一段 encryptedData，返回 [encryptedData, sessionKey, iv]
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

    public function testUnionDecryptWechatMini(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $data      = $union->decrypt(Channel::WechatMini, $encrypted, $sessionKey, $iv);

        self::assertSame('Band', $data['nickName']);
    }

    public function testUnionDecryptWechatApp(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'nickName'  => 'AppUser',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $data      = $union->decrypt(Channel::WechatApp, $encrypted, $sessionKey, $iv);

        self::assertSame('AppUser', $data['nickName']);
    }

    public function testUnionDecryptDouyinMini(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'nickName'  => 'DouyinUser',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $data = $union->decrypt(Channel::DouyinMini, $encrypted, $sessionKey, $iv);

        self::assertSame('DouyinUser', $data['nickName']);
    }

    public function testUnionDecryptQq(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'nickName'  => 'QqUser',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $data = $union->decrypt(Channel::Qq, $encrypted, $sessionKey, $iv);

        self::assertSame('QqUser', $data['nickName']);
    }

    public function testUnsupportedChannelThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持客户端敏感数据解密');
        $union->decrypt(Channel::AlipayMini, 'x', 'y', 'z');
    }
}
