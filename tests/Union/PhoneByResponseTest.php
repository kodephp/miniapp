<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use InvalidArgumentException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Utils\Sign;

/**
 * Union 层统一「支付宝 response + sign 换取手机号」入口测试
 *
 * 端到端真实解密链路（AES-128-CBC + 全零 IV），复用 Alipay 解密测试的同款加密辅助；
 * 验签链路复用 tests/fixtures 中的 RSA 密钥对。验证：
 *   - 支付宝各渠道（mini / mp / app）成功换取并归一化手机号
 *   - 传入合法 sign 验签通过；传入错误 sign 抛 ApiException
 *   - 非支付宝渠道调用应抛 InvalidArgumentException（保持 fence 语义明确）
 */
final class PhoneByResponseTest extends TestCase
{
    private const APP_ID = 'alipayapp0000000000';

    private function fixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../fixtures/' . $name);
        \assert(is_string($content));

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
     * @param array<string, mixed> $alipayConfig
     */
    private function makeUnion(array $alipayConfig = []): Union
    {
        $aesKey = base64_encode(random_bytes(16));

        return (new Kernel([
            'alipay' => array_merge([
                'app_id'  => self::APP_ID,
                'aes_key' => $aesKey,
            ], $alipayConfig),
        ]))->union();
    }

    public function testPhoneByResponseAlipayMiniSuccess(): void
    {
        $aesKey  = random_bytes(16);
        $union   = $this->makeUnion(['aes_key' => base64_encode($aesKey)]);
        $payload = ['mobile' => '13800138000', 'countryCode' => '86'];
        $response = $this->encrypt($payload, $aesKey);

        $info = $union->phoneByResponse(Channel::AlipayMini, $response);

        self::assertSame('13800138000', $info['mobile']);
        self::assertSame('86', $info['countryCode']);
        // 归一化三元组（与微信 / 抖音一致）
        self::assertSame('13800138000', $info['phoneNumber']);
        self::assertSame('13800138000', $info['purePhoneNumber']);
    }

    public function testPhoneByResponseAlipayMpAndAppAccepted(): void
    {
        $aesKey  = random_bytes(16);
        $union   = $this->makeUnion(['aes_key' => base64_encode($aesKey)]);
        $response = $this->encrypt(['mobile' => '13900139000'], $aesKey);

        foreach ([Channel::AlipayMp, Channel::AlipayApp] as $channel) {
            $info = $union->phoneByResponse($channel, $response);
            self::assertSame('13900139000', $info['mobile']);
        }
    }

    public function testPhoneByResponseWithValidSign(): void
    {
        $privateKey = $this->fixture('rsa_private.pem');
        $publicKey  = $this->fixture('rsa_public.pem');

        $aesKey   = random_bytes(16);
        $union    = $this->makeUnion(['aes_key' => base64_encode($aesKey), 'public_key' => $publicKey]);
        $response = $this->encrypt(['mobile' => '13800138000'], $aesKey);
        $sign     = Sign::rsaRaw($response, $privateKey, 'sha256');

        $info = $union->phoneByResponse(Channel::AlipayMini, $response, $sign);

        self::assertSame('13800138000', $info['mobile']);
    }

    public function testPhoneByResponseWithBadSignThrows(): void
    {
        $privateKey = $this->fixture('rsa_private.pem');
        $publicKey  = $this->fixture('rsa_public.pem');

        $aesKey   = random_bytes(16);
        $union    = $this->makeUnion(['aes_key' => base64_encode($aesKey), 'public_key' => $publicKey]);
        $response = $this->encrypt(['mobile' => '13800138000'], $aesKey);
        $badSign  = Sign::rsaRaw('tampered-response', $privateKey, 'sha256');

        $this->expectException(\Kode\MiniApp\Exceptions\ApiException::class);
        $this->expectExceptionMessage('验签');
        $union->phoneByResponse(Channel::AlipayMini, $response, $badSign);
    }

    public function testPhoneByResponseNonAlipayThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('response + sign');
        $union->phoneByResponse(Channel::WechatMini, 'whatever');
    }
}
