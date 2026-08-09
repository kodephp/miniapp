<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use InvalidArgumentException;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Douyin\DouyinApp;
use Kode\MiniApp\Providers\Qq\QqApp;
use Kode\MiniApp\Providers\Wechat\WechatApp;
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
            'alipay' => [
                'app_id' => self::APP_ID,
                'aes_key' => base64_encode(random_bytes(16)),
            ],
        ]))->union();
    }

    /**
     * 按支付宝官方算法（AES-128-CBC + 全零 IV）加密明文，返回 base64 密文
     *
     * @param array<string, mixed> $payload
     */
    private function encryptAlipay(array $payload, string $aesKeyRaw): string
    {
        $plain = json_encode($payload);
        \assert(is_string($plain));
        $iv     = str_repeat("\0", 16);
        $cipher = openssl_encrypt($plain, 'aes-128-cbc', $aesKeyRaw, OPENSSL_RAW_DATA, $iv);
        \assert(is_string($cipher));

        return base64_encode($cipher);
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

    public function testUnionAlipayDecryptPhone(): void
    {
        $aesKey  = random_bytes(16);
        $payload = ['mobile' => '13800138000', 'countryCode' => '86'];

        // 支付宝解密算法无 sessionKey/iv，经 Union::alipay()->decrypt() 访问（与 4 参 decrypt 签名不兼容）
        $union = (new \Kode\MiniApp\Kernel([
            'alipay' => [
                'app_id' => self::APP_ID,
                'aes_key' => base64_encode($aesKey),
            ],
        ]))->union();

        $app     = $union->alipay()->appInstance();
        \assert($app instanceof \Kode\MiniApp\Providers\Alipay\AlipayApp);

        $response = $this->encryptAlipay($payload, $aesKey);
        $result   = $app->decrypt()->phone($response);

        self::assertSame('13800138000', $result['mobile']);
    }

    public function testUnsupportedChannelThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持客户端敏感数据解密');
        $union->decrypt(Channel::AlipayMini, 'x', 'y', 'z');
    }

    /**
     * 一键式解密：登录托管的 session_key 自动取用（微信 / 抖音 / QQ 三端）
     */
    public function testUnionDecryptByUser(): void
    {
        $cache = new ArrayCache();
        $kernel = new Kernel([
            'wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret', 'cache' => $cache],
            'douyin' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret', 'cache' => $cache],
            'qq'     => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret', 'cache' => $cache],
        ]);
        $union = $kernel->union();

        $payload = [
            'nickName'  => 'Band',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        // 微信
        [$wxEnc, $wxSk, $wxIv] = $this->encrypt($payload);
        $wxApp = $kernel->wechat()->app();
        \assert($wxApp instanceof WechatApp);
        SessionKeyManager::for($wxApp->config())->store('openid-wx', $wxSk);
        self::assertSame('Band', $union->decryptByUser(Channel::WechatMini, $wxEnc, $wxIv, 'openid-wx')['nickName']);

        // 抖音
        [$dyEnc, $dySk, $dyIv] = $this->encrypt($payload);
        $dyApp = $kernel->douyin()->app();
        \assert($dyApp instanceof DouyinApp);
        SessionKeyManager::for($dyApp->config())->store('openid-dy', $dySk);
        self::assertSame('Band', $union->decryptByUser(Channel::DouyinMini, $dyEnc, $dyIv, 'openid-dy')['nickName']);

        // QQ
        [$qqEnc, $qqSk, $qqIv] = $this->encrypt($payload);
        $qqApp = $kernel->qq()->app();
        \assert($qqApp instanceof QqApp);
        SessionKeyManager::for($qqApp->config())->store('openid-qq', $qqSk);
        self::assertSame('Band', $union->decryptByUser(Channel::Qq, $qqEnc, $qqIv, 'openid-qq')['nickName']);
    }

    public function testUnionDecryptByUserMissingCacheThrows(): void
    {
        $cache = new ArrayCache();
        $kernel = new Kernel([
            'wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret', 'cache' => $cache],
        ]);
        $union = $kernel->union();

        [$encrypted] = $this->encrypt([
            'nickName'  => 'Band',
            'watermark' => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ]);

        $this->expectException(\Kode\MiniApp\Exceptions\ApiException::class);
        $this->expectExceptionMessage('未找到用户');
        $union->decryptByUser(Channel::WechatMini, $encrypted, base64_encode(random_bytes(16)), 'unknown');
    }
}
