<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Support\Encryptor;
use PHPUnit\Framework\TestCase;

/**
 * 微信 V3 入站通知「验签 + 解密」双链 e2e（补齐 verifyNotify 仅解密、不验签的历史缺口）。
 *
 * 微信 V3 回调安全由两段构成：
 *   1) RSA-SHA256 验签（Wechatpay-Signature / Timestamp / Nonce / Serial，依赖平台证书公钥）
 *   2) resource 的 AES-256-GCM 解密（依赖 api_v3_key）
 * 此前桥接的 verifyNotify 在 V3 分支**只解密不验签**（由 HTTP 边界兜底），本测试证明新增的
 * {@see \Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter::verifyWebhook()} 能在桥接内完成
 * 「验签 → 解密」完整闭环，复用 kode/pays WechatPayV3Gateway 的同名方法，无静默通过。
 */
final class PaysBridgeWechatV3WebhookVerifyTest extends TestCase
{
    private const API_V3_KEY = '0123456789abcdef0123456789abcdef';

    private const AAD = 'wechat-pay-v3-notify';

    private const SERIAL = 'TEST_SERIAL_NO_001';

    private string $privateKeyPem;

    private string $publicKeyPem;

    protected function setUp(): void
    {
        // 生成一对 RSA 密钥：私钥用于「签名」模拟微信平台，公钥（平台证书）用于验签。
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
            'bits' => 2048,
        ]);
        self::assertNotFalse($res, 'openssl 必须可用以生成测试 RSA 密钥对');
        $priv = '';
        openssl_pkey_export($res, $priv);
        $this->privateKeyPem = $priv;
        $details = openssl_pkey_get_details($res);
        self::assertNotFalse($details, '无法读取 RSA 公钥');
        $this->publicKeyPem = (string) $details['key'];
    }

    /**
     * 以与 WechatPayV3Gateway::decryptResource 完全一致的格式构造一段 V3 密文 resource。
     *
     * @param array<string, mixed> $business
     * @return array<string, mixed>
     */
    private function encryptedResource(array $business): array
    {
        $enc = Encryptor::aesGcmEncrypt((string) json_encode($business), self::API_V3_KEY, self::AAD);

        $cipherBytes = base64_decode($enc['ciphertext'], true);
        $tagBytes = base64_decode($enc['tag'], true);

        return [
            'ciphertext' => base64_encode($cipherBytes . $tagBytes),
            'nonce' => $enc['nonce'],
            'associated_data' => self::AAD,
        ];
    }

    /**
     * V3 网关所需 config（含 platform_certificate = 平台证书公钥，用于验签）。
     *
     * @return array<string, mixed>
     */
    private function wechatConfig(): array
    {
        return [
            'app_id' => 'wx_app',
            'mch_id' => 'mch_1',
            'api_key' => 'unit_test_api_key_0123456789',
            'api_v3_key' => self::API_V3_KEY,
            'serial_no' => self::SERIAL,
            'private_key' => $this->privateKeyPem,
            'platform_certificate' => $this->publicKeyPem,
        ];
    }

    /**
     * 用私钥对 `timestamp\nnonce\nbody\n` 签名，拼出微信 V3 回调所需的 Wechatpay-* 头。
     *
     * @param string $body 原始请求体（JSON 字符串）
     * @return array<string, string>
     */
    private function signedHeaders(string $body): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));
        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $signature = Encryptor::rsaSign($message, $this->privateKeyPem, 'sha256');

        return [
            'Wechatpay-Signature' => $signature,
            'Wechatpay-Timestamp' => $timestamp,
            'Wechatpay-Nonce' => $nonce,
            'Wechatpay-Serial' => self::SERIAL,
        ];
    }

    public function testV3WebhookVerifiesSignatureThenDecryptsResource(): void
    {
        $business = [
            'out_trade_no' => 'T_WH_20260817',
            'transaction_id' => 'TXN_WH_4200001234567890',
            'trade_state' => 'SUCCESS',
            'amount' => ['total' => 100, 'currency' => 'CNY'],
        ];

        $body = (string) json_encode([
            'id' => 'EV-20260817-WH-001',
            'resource_type' => 'encrypt-resource',
            'resource' => $this->encryptedResource($business),
        ]);

        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        /** @var array<string, mixed> $result */
        $result = $adapter->verifyWebhook($body, $this->signedHeaders($body));

        // 验签 + 解密后应为原始业务明文，且字段完整保留
        self::assertSame('T_WH_20260817', $result['out_trade_no'] ?? '');
        self::assertSame('TXN_WH_4200001234567890', $result['transaction_id'] ?? '');
        self::assertSame('SUCCESS', $result['trade_state'] ?? '');
        self::assertSame(100, $result['amount']['total'] ?? null);
    }

    public function testV3WebhookViaNotifyAdapterFacade(): void
    {
        $business = ['out_trade_no' => 'T_WH_FACADE', 'trade_state' => 'PAID'];

        $body = (string) json_encode([
            'resource' => $this->encryptedResource($business),
        ]);

        $notify = PaysBridge::notifyAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

        /** @var array<string, mixed> $result */
        $result = $notify->verifyWebhook($body, $this->signedHeaders($body));

        self::assertSame('T_WH_FACADE', $result['out_trade_no'] ?? '');
    }

    public function testTamperedSignatureRejectedNoSilentPass(): void
    {
        $body = (string) json_encode([
            'resource' => $this->encryptedResource(['out_trade_no' => 'T_WH_TAMPER']),
        ]);

        // 篡改签名：用一个错误私钥重签，平台证书验签必失败
        $fake = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256', 'bits' => 2048]);
        self::assertNotFalse($fake, 'openssl 必须可用以生成测试 RSA 密钥对');
        $fakePriv = '';
        openssl_pkey_export($fake, $fakePriv);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));
        $badSig = Encryptor::rsaSign($timestamp . "\n" . $nonce . "\n" . $body . "\n", $fakePriv, 'sha256');

        $headers = [
            'Wechatpay-Signature' => $badSig,
            'Wechatpay-Timestamp' => $timestamp,
            'Wechatpay-Nonce' => $nonce,
            'Wechatpay-Serial' => self::SERIAL,
        ];

        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('微信 V3 支付回调验签失败');

        $adapter->verifyWebhook($body, $headers);
    }

    public function testMissingWechatpayHeadersRejected(): void
    {
        $body = (string) json_encode([
            'resource' => $this->encryptedResource(['out_trade_no' => 'T_WH_NOHDR']),
        ]);

        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('微信 V3 支付回调验签失败');

        // 完全不传 Wechatpay-* 头，verifyWebhook 必返回 false → 验签失败
        $adapter->verifyWebhook($body, []);
    }

    public function testMissingPlatformCertificateRejected(): void
    {
        $body = (string) json_encode([
            'resource' => $this->encryptedResource(['out_trade_no' => 'T_WH_NOCERT']),
        ]);

        // 提供 V3 网关构造所需的 serial_no / private_key，但故意缺 platform_certificate，
        // 触发验签时无平台证书可用 → 验签失败。
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => [
            'app_id' => 'wx_app',
            'mch_id' => 'mch_1',
            'api_key' => 'unit_test_api_key_0123456789',
            'api_v3_key' => self::API_V3_KEY,
            'serial_no' => self::SERIAL,
            'private_key' => $this->privateKeyPem,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('缺少平台证书');

        $adapter->verifyWebhook($body, $this->signedHeaders($body));
    }

    public function testNonWechatChannelRejected(): void
    {
        $adapter = PaysBridge::adapter(Channel::AlipayMini, fn () => [
            'app_id' => 'ali_app',
            'private_key' => $this->privateKeyPem,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('verifyWebhook 仅支持微信渠道');

        $adapter->verifyWebhook('{}', []);
    }
}
