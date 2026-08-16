<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 支付宝异步回调验签（RSA2）端到端测试。
 *
 * 与微信 V2 的 MD5 验签（{@see PaysBridgeNotifyVerifyTest}）对称，但合约完全不同：
 * 支付宝回调走 RSA2（SHA256 + 公钥验签），是跨渠道「钱到账确认路径」的最后一环。
 * 全程不触网——用测试内生成的 RSA2 密钥对自签报文，证明桥接只做路由、验签由真实网关完成。
 */
final class PaysBridgeAlipayNotifyVerifyTest extends TestCase
{
    private const APP_ID = '2014072300007148';

    private string $privateKey;

    private string $publicKey;

    protected function setUp(): void
    {
        // 生成 RSA2 密钥对（2048-bit），支付宝 notify 验签使用 SHA256 + 公钥
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
            'bits' => 2048,
        ]);
        self::assertNotFalse($res, 'openssl RSA 密钥生成失败');

        $private = '';
        openssl_pkey_export($res, $private);
        $detail = openssl_pkey_get_details($res);
        self::assertIsArray($detail);

        $this->privateKey = $private;
        $this->publicKey = (string) $detail['key'];

        // 避免跨测试缓存到其它 alipay 网关配置
        Pay::clearCache('alipay');
    }

    /**
     * @return array<string, mixed>
     */
    private function alipayConfig(): array
    {
        return [
            'app_id' => self::APP_ID,
            'private_key' => $this->privateKey,
            'public_key' => $this->publicKey,
        ];
    }

    /**
     * 用与 AlipayGateway::verifyNotify 完全一致的规则自签报文。
     *
     * 网关验签时会先移除 sign 与 sign_type，再对剩余字段 ksort 后 buildQueryString
     * （排除空值）→ SHA256 → base64。因此签名也必须基于「移除 sign_type 后的集合」，
     * 否则规范化字符串不一致导致验签失败。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function signedNotify(array $data): array
    {
        // 网关验签前会移除 sign + sign_type，故签名基串不含这两项
        $toSign = $data;
        unset($toSign['sign'], $toSign['sign_type']);

        $string = Signer::buildQueryString($toSign);
        openssl_sign($string, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $data['sign_type'] = 'RSA2';
        $data['sign'] = base64_encode((string) $signature);

        return $data;
    }

    public function testValidSignedAlipayNotifyDecodesThroughBridge(): void
    {
        $payload = $this->signedNotify([
            'app_id' => self::APP_ID,
            'out_trade_no' => 'MINIAPP_20260816_0001',
            'trade_no' => '2026081622001234567890',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '88.00',
            'seller_id' => '2088102146225135',
            'timestamp' => '2026-08-16 09:00:00',
        ]);

        $data = PaysBridge::notifyAdapter(Channel::AlipayMini, fn () => $this->alipayConfig())
            ->decode($payload);

        // 验签通过：桥接返回可信业务数组（不再重新抛错）
        self::assertArrayHasKey('out_trade_no', $data);
        self::assertSame('MINIAPP_20260816_0001', $data['out_trade_no']);
        self::assertSame('TRADE_SUCCESS', $data['trade_status']);
        self::assertSame(self::APP_ID, $data['app_id']);
    }

    public function testTamperedAlipayNotifyIsRejected(): void
    {
        $payload = $this->signedNotify([
            'app_id' => self::APP_ID,
            'out_trade_no' => 'MINIAPP_20260816_0001',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '88.00',
        ]);

        // 篡改业务字段但不重签 → 验签必失败 → 桥接抛 RuntimeException（无静默放行）
        $payload['out_trade_no'] = 'TAMPERED_ORDER';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('支付回调验签失败');

        PaysBridge::notifyAdapter(Channel::AlipayMini, fn () => $this->alipayConfig())
            ->decode($payload);
    }

    public function testMissingSignAlipayNotifyIsRejected(): void
    {
        $payload = [
            'app_id' => self::APP_ID,
            'out_trade_no' => 'MINIAPP_20260816_0001',
            'trade_status' => 'TRADE_SUCCESS',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('支付回调验签失败');

        PaysBridge::notifyAdapter(Channel::AlipayMini, fn () => $this->alipayConfig())
            ->decode($payload);
    }

    public function testSignatureIsGenuineRsa2NotStub(): void
    {
        $payload = $this->signedNotify([
            'app_id' => self::APP_ID,
            'out_trade_no' => 'MINIAPP_20260816_0001',
            'trade_status' => 'TRADE_SUCCESS',
        ]);

        $sign = (string) $payload['sign'];
        unset($payload['sign'], $payload['sign_type']);

        // 用网关同款 verifyRsa 独立重算，证明报文真的是 RSA2 签名（而非桩）
        self::assertTrue(
            Signer::verifyRsa($payload, $this->publicKey, $sign, false, 'SHA256'),
            '自签报文未能通过独立 RSA2 验签',
        );
    }
}
