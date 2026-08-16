<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Union\Bridge\Fixtures\V3SigningFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 余额查询 / 结算查询 真实网关签名链与能力守卫 e2e。
 *
 * - settlementQuery 路由到 querySettlement → queryTransfer（V3 signedV3Get），
 *   产生真实 V3 证书签名头（与 transferQuery 共用底层 V3 路径，验证「结算查询」业务入口路由正确）。
 * - balanceQuery 在微信（WechatPayGateway 未实现 queryBalance）上经 callGatewayFeature 的
 *   method_exists 守卫直接抛 RuntimeException（非静默成功），supportsBalance()=false，
 *   证明余额能力在微信 V2 渠道上明确不可用而非悄悄下行。
 */
class PaysBridgeBalanceSettlementQueryTest extends TestCase
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

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    private function adapter(): PaysBridgePayAdapter
    {
        return PaysBridge::adapter(Channel::WechatMini, fn () => [
            'app_id'      => 'wx_app',
            'mch_id'      => 'mch_1',
            'api_key'     => 'unit_test_api_key_0123456789',
            'serial_no'   => 'TEST_SERIAL_NO_001',
            'private_key' => $this->privateKeyPem,
        ]);
    }

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

        $mch    = (string) ($m['mch'] ?? '');
        $serial = (string) ($m['serial'] ?? '');
        $ts     = (string) ($m['ts'] ?? '');
        $nonce  = (string) ($m['nonce'] ?? '');
        $sig    = (string) ($m['sig'] ?? '');
        self::assertSame('mch_1', $mch, 'Authorization 必须带正确 mchid');
        self::assertSame('TEST_SERIAL_NO_001', $serial, 'Authorization 必须带配置中的 serial_no');
        self::assertNotEmpty($ts, 'Authorization 必须带 timestamp');
        self::assertNotEmpty($nonce, 'Authorization 必须带 nonce_str');

        // 规范化路径：网关把相对端点拼接基础地址后出站，故 lastUrl 为完整 URL，
        // 取 URL 路径部分即与 WechatV3SigningTrait::canonicalPath 参与签名的路径一致。
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

    public function testSettlementQueryProducesV3AuthorizationHeaderViaRealGateway(): void
    {
        $result = $this->adapter()->settlementQuery('SETTLE_20260816');

        self::assertSame('GET', $this->fake->lastMethod, '结算查询应走 V3 GET');
        self::assertArrayHasKey('batch_status', $result, '网关应直返 V3 JSON 解析后的数组');
        self::assertSame('ACCEPTED', $result['batch_status']);

        $this->assertV3SignatureValid('GET');
    }

    public function testBalanceQueryUnsupportedOnWechatThrowsClearRuntimeException(): void
    {
        self::assertFalse(
            $this->adapter()->supportsBalance(),
            '微信 WechatPayGateway 未实现 queryBalance，能力发现应返回 false',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('余额');
        $this->adapter()->balanceQuery();
    }
}
