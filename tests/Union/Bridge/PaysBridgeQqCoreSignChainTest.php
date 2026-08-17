<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Union\Bridge\Fixtures\V3SigningFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use PHPUnit\Framework\TestCase;

/**
 * QQ 支付核心下单 / 退款 / 关单 V3 RSA-SHA256 签名链 e2e。
 *
 * QQ 支付协议复用微信 V3 签名特质（{@see \Kode\Pays\Gateway\Wechat\WechatV3SigningTrait}），
 * 故请求以商户私钥（private_key）对 `METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY\n` 完成 RSA-SHA256 签名，
 * 并带 WECHATPAY2-SHA256-RSA2048 Authorization 头（含 mchid / serial_no）。本测试复用 V3 假客户端
 * 拦截 QQ 网关出站请求，用「同一密钥对的公钥」做 openssl_verify 独立复核，证明 QQ 的真实 V3 签名
 * 链可被外部验证——与微信 V3 测试（{@see PaysBridgeV3SignChainTest}）同属强证据。
 *
 * closeOrder 在 QQ 网关真实支持（不同于抖音），故断言成功返回；抖音侧对应断言为大声失败。
 */
final class PaysBridgeQqCoreSignChainTest extends TestCase
{
    private V3SigningFakeHttpClient $fake;

    private string $privateKeyPem;

    private string $publicKeyPem;

    protected function setUp(): void
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        self::assertNotFalse($res, 'openssl 必须可用以生成测试 RSA 密钥');

        $priv = '';
        openssl_pkey_export($res, $priv);
        $this->privateKeyPem = $priv;

        $detail = openssl_pkey_get_details($res);
        self::assertNotFalse($detail, 'openssl_pkey_get_details 必须返回密钥明细');
        $this->publicKeyPem = (string) $detail['key'];

        $this->fake = new V3SigningFakeHttpClient();
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    private function adapter(): PaysBridgePayAdapter
    {
        return PaysBridge::adapter(Channel::Qq, fn () => [
            'app_id'      => 'qq_app',
            'mch_id'      => 'mch_1',
            'api_key'     => 'unit_test_api_key_0123456789',
            'serial_no'   => 'TEST_SERIAL_NO_QQ_001',
            'private_key' => $this->privateKeyPem,
        ]);
    }

    /**
     * 校验最近一次出站的 V3 Authorization 头格式，并用同一 RSA 公钥验签通过。
     */
    private function assertV3SignatureValid(string $method): void
    {
        $auth = (string) ($this->fake->lastHeaders['Authorization'] ?? '');
        self::assertStringStartsWith('WECHATPAY2-SHA256-RSA2048', $auth);

        $m = [];
        preg_match(
            '/mchid="(?P<mch>[^"]*)",serial_no="(?P<serial>[^"]*)",'
            . 'timestamp="(?P<ts>[^"]*)",nonce_str="(?P<nonce>[^"]*)",signature="(?P<sig>[^"]*)"/',
            $auth,
            $m,
        );

        $mch     = (string) ($m['mch'] ?? '');
        $serial  = (string) ($m['serial'] ?? '');
        $ts      = (string) ($m['ts'] ?? '');
        $nonce   = (string) ($m['nonce'] ?? '');
        $sig     = (string) ($m['sig'] ?? '');
        self::assertSame('mch_1', $mch, 'Authorization 必须带正确 mchid');
        self::assertSame('TEST_SERIAL_NO_QQ_001', $serial, 'Authorization 必须带配置中的 serial_no');
        self::assertNotEmpty($ts, 'Authorization 必须带 timestamp');
        self::assertNotEmpty($nonce, 'Authorization 必须带 nonce_str');

        $rawUrl = $this->fake->lastUrl;
        $path   = str_starts_with($rawUrl, 'http')
            ? (string) parse_url($rawUrl, PHP_URL_PATH)
            : '/' . ltrim($rawUrl, '/');
        $body    = $method === 'POST' ? $this->fake->lastRawBody : '';
        $message = $method . "\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . $body . "\n";

        $signature = (string) base64_decode($sig, true);
        $ok        = openssl_verify($message, $signature, $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, 'V3 签名必须能用同一 RSA 公钥独立验签通过');
    }

    public function testCreateOrderSignsV3AuthorizationHeader(): void
    {
        $result = $this->adapter()->createOrder([
            'out_trade_no' => 'Q20260817',
            'total_amount' => 100,
            'trade_type'   => 'NATIVE',
        ]);

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('v3/pay/transaction/jsapi', $this->fake->lastUrl);
        $this->assertV3SignatureValid('POST');
        self::assertSame('Q20260817', $result['out_trade_no'] ?? null);
    }

    public function testQueryOrderSignsV3AuthorizationHeader(): void
    {
        $this->adapter()->queryOrder('Q20260817');

        self::assertSame('GET', $this->fake->lastMethod);
        self::assertStringContainsString('v3/pay/transaction/out-trade-no/Q20260817', $this->fake->lastUrl);
        $this->assertV3SignatureValid('GET');
    }

    public function testRefundSignsV3AuthorizationHeader(): void
    {
        $result = $this->adapter()->refund([
            'out_trade_no'  => 'Q20260817',
            'out_refund_no' => 'QR20260817',
            'refund_fee'    => 100,
            'total_fee'     => 100,
        ]);

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('v3/refund/domestic/refunds', $this->fake->lastUrl);
        $this->assertV3SignatureValid('POST');
        self::assertSame('QR20260817', $result['out_refund_no'] ?? null);
    }

    public function testQueryRefundSignsV3AuthorizationHeader(): void
    {
        $this->adapter()->queryRefund('QR20260817');

        self::assertSame('GET', $this->fake->lastMethod);
        self::assertStringContainsString('v3/refund/domestic/refunds/QR20260817', $this->fake->lastUrl);
        $this->assertV3SignatureValid('GET');
    }

    public function testCloseOrderSignsV3AuthorizationHeaderAndSucceeds(): void
    {
        $result = $this->adapter()->closeOrder('Q20260817');

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('v3/pay/transaction/out-trade-no/Q20260817/close', $this->fake->lastUrl);
        $this->assertV3SignatureValid('POST');
        self::assertSame('Q20260817', $result['out_trade_no'] ?? null);
        self::assertSame('SUCCESS', $result['result'] ?? null);
    }
}
