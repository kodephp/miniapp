<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Support\Encryptor;
use PHPUnit\Framework\TestCase;

/**
 * 微信 V3 入站通知解密 e2e（修复历史死代码分支）。
 *
 * 桥接此前把 Wechat* 解析到 V2 网关（WechatPayGateway，无 decryptResource），
 * 导致 {@see \Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter::verifyNotify()} 中
 * 的 V3 解密分支永远不触发（对所有渠道均为死代码），V3 通知密文无法还原。
 *
 * 本测试证明：verifyNotify 在检测到 `resource.ciphertext` 时会**显式**取
 * `WechatPayV3Gateway` 实例，用与网关一致的 api_v3_key 走真实 AES-256-GCM 解密，
 * 还原明文业务数据；密文被篡改 / 缺 api_v3_key 时归一为 ApiException（无静默成功）。
 */
final class PaysBridgeWechatV3NotifyDecryptTest extends TestCase
{
    // APIv3 密钥必须为 32 字节
    private const API_V3_KEY = '0123456789abcdef0123456789abcdef';

    private const AAD = 'wechat-pay-v3-notify';

    private string $privateKeyPem;

    protected function setUp(): void
    {
        // V3 网关构造需要 mch_id / serial_no / private_key / api_key，
        // 这里生成一对仅用于构造（解密只用 api_v3_key），私钥内容不做真实签名校验。
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
            'bits' => 2048,
        ]);
        self::assertNotFalse($res, 'openssl 必须可用以生成测试 RSA 私钥');
        $priv = '';
        openssl_pkey_export($res, $priv);
        $this->privateKeyPem = $priv;
    }

    /**
     * 用与 WechatPayV3Gateway::decryptResource 完全一致的格式构造一段 V3 密文 resource。
     *
     * decryptResource 期望 resource.ciphertext = base64(密文原文 + 16 字节 GCM tag)，
     * 与微信 V3 通知线上格式一致；本方法直接复用 kode/pays 的 Encryptor 生成，确保算法参数同源。
     *
     * @return array<string, mixed>
     */
    /**
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
     * @return array<string, mixed>
     */
    private function wechatConfig(): array
    {
        return [
            'app_id' => 'wx_app',
            'mch_id' => 'mch_1',
            'api_key' => 'unit_test_api_key_0123456789',
            'api_v3_key' => self::API_V3_KEY,
            'serial_no' => 'TEST_SERIAL_NO_001',
            'private_key' => $this->privateKeyPem,
        ];
    }

    public function testV3NotifyDecryptsCiphertextToPlaintextBusinessArray(): void
    {
        $business = [
            'out_trade_no' => 'T_V3_20260817',
            'transaction_id' => 'TXN_V3_4200001234567890',
            'trade_state' => 'SUCCESS',
            'amount' => ['total' => 100, 'currency' => 'CNY'],
        ];

        $payload = [
            'id' => 'EV-20260817-001',
            'resource_type' => 'encrypt-resource',
            'resource' => $this->encryptedResource($business),
        ];

        $notify = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        /** @var array<string, mixed> $result */
        $result = $notify->verifyNotify($payload);

        // 解密后应为原始业务明文，且字段完整保留
        self::assertSame('T_V3_20260817', $result['out_trade_no'] ?? '');
        self::assertSame('TXN_V3_4200001234567890', $result['transaction_id'] ?? '');
        self::assertSame('SUCCESS', $result['trade_state'] ?? '');
        self::assertSame(100, $result['amount']['total'] ?? null);
    }

    public function testV3NotifyDecryptViaNotifyAdapterFacade(): void
    {
        $business = ['out_trade_no' => 'T_V3_FACADE', 'trade_state' => 'PAID'];

        $payload = [
            'resource' => $this->encryptedResource($business),
        ];

        $notify = PaysBridge::notifyAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

        /** @var array<string, mixed> $result */
        $result = $notify->decode($payload);

        self::assertSame('T_V3_FACADE', $result['out_trade_no'] ?? '');
    }

    public function testTamperedCiphertextRejectedNoSilentSuccess(): void
    {
        $resource = $this->encryptedResource(['out_trade_no' => 'T_TAMPER']);

        // 篡改密文（翻转末位字节）后 GCM 认证必失败
        $raw = (string) base64_decode($resource['ciphertext'], true);
        $raw[0] = $raw[0] === 'A' ? 'B' : 'A';
        $resource['ciphertext'] = base64_encode($raw);

        $notify = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $this->expectException(ApiException::class);

        $notify->verifyNotify(['resource' => $resource]);
    }

    public function testMissingApiV3KeyNormalizedToApiException(): void
    {
        $resource = $this->encryptedResource(['out_trade_no' => 'T_NOKEY']);

        // 提供 V3 网关构造所必需的 serial_no / private_key，但故意缺 api_v3_key，
        // 触发 decryptResource 的 configError（缺少 api_v3_key）。
        $notify = PaysBridge::adapter(Channel::WechatMini, fn () => [
            'app_id' => 'wx_app',
            'mch_id' => 'mch_1',
            'api_key' => 'unit_test_api_key_0123456789',
            'serial_no' => 'TEST_SERIAL_NO_001',
            'private_key' => $this->privateKeyPem,
        ]);

        $this->expectException(ApiException::class);

        $notify->verifyNotify(['resource' => $resource]);
    }

    public function testV2NotifyStillHandledByV2Gateway(): void
    {
        // 无 resource.ciphertext 的 V2 通知不应进入 V3 分支（走 V2 网关 verifyNotify）
        $notify = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('支付回调验签失败');

        // 缺 sign 的 V2 报文 → V2 网关 verifyNotify 返回 false → 桥接抛 RuntimeException
        $notify->verifyNotify(['out_trade_no' => 'T_V2', 'result_code' => 'SUCCESS']);
    }
}
