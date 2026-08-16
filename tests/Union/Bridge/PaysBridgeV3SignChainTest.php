<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\V3SigningFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Core\PayException;
use Kode\Pays\Facade\Pay;
use PHPUnit\Framework\TestCase;

/**
 * 微信 APIv3 证书能力签名链 e2e。
 *
 * 目标：证明真实 WechatPayGateway 在三个 V3 方法（transferBatch / transferQuery /
 * transferReceipt）上，确实以商户私钥（private_key）对微信 V3 规范化串
 * `METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY\n` 完成 RSA-SHA256 签名，并带正确 serial_no /
 * mchid。验证用「同一密钥对的公钥」做 openssl_verify，与 V2 测试用 Signer::verifyMd5
 * 重算同属「真实网关签名可被独立复核」的强证据。
 *
 * 这三个方法仅微信 V3 提供，离线无法构造合法 V3 平台证书响应，故测试聚焦**出站签名链**
 * （Authorization 头格式 + 公钥验签），响应体由 V3SigningFakeHttpClient 返回 JSON 直返，
 * 不经过解密即可断言关键字段。
 */
final class PaysBridgeV3SignChainTest extends TestCase
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

    /**
     * 构造带 serial_no + private_key 的微信小程序适配器（V3 方法必须）。
     */
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

        $mch   = (string) ($m['mch'] ?? '');
        $serial = (string) ($m['serial'] ?? '');
        $ts    = (string) ($m['ts'] ?? '');
        $nonce = (string) ($m['nonce'] ?? '');
        $sig   = (string) ($m['sig'] ?? '');
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

    public function testTransferBatchSignsV3AuthorizationHeaderViaRealGateway(): void
    {
        $result = $this->adapter()->transferBatch([
            'out_biz_no'          => 'B20260816',
            'transfer_detail_list' => [
                [
                    'out_detail_no' => 'D1',
                    'amount'        => 100,
                    'remark'        => 't1',
                    'recipient'     => ['account' => 'OPENID_A', 'name' => '张三'],
                ],
            ],
        ]);

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('v3/transfer/batches', $this->fake->lastUrl);
        $this->assertV3SignatureValid('POST');

        // 响应体由网关解析为关联数组（V3 成功 JSON 直返，不经解密）
        self::assertSame('B20260816', $result['out_batch_no'] ?? null);
    }

    public function testTransferQuerySignsV3AuthorizationHeaderViaRealGateway(): void
    {
        $result = $this->adapter()->transferQuery('B20260816');

        self::assertSame('GET', $this->fake->lastMethod);
        self::assertStringContainsString('out-batch-no/B20260816', $this->fake->lastUrl);
        $this->assertV3SignatureValid('GET');

        self::assertSame('ACCEPTED', $result['batch_status'] ?? null);
    }

    public function testTransferReceiptSignsV3AuthorizationHeaderViaRealGateway(): void
    {
        $result = $this->adapter()->transferReceipt('B20260816');

        self::assertSame('GET', $this->fake->lastMethod);
        self::assertStringContainsString('electronic-receipt', $this->fake->lastUrl);
        $this->assertV3SignatureValid('GET');

        self::assertSame('BATCH_001', $result['batch_id'] ?? null);
    }

    public function testMissingSerialNoNormalizedToApiException(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => [
            'app_id'  => 'wx_app',
            'mch_id'  => 'mch_1',
            'api_key' => 'unit_test_api_key_0123456789',
            // 故意缺 serial_no / private_key
        ]);

        $caught = null;
        try {
            $adapter->transferBatch([
                'out_biz_no'           => 'B20260816',
                'transfer_detail_list' => [
                    ['out_detail_no' => 'D1', 'amount' => 100, 'recipient' => ['account' => 'OPENID_A']],
                ],
            ]);
        } catch (ApiException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, '缺少 serial_no 必须归一为 ApiException（无静默失败）');
        self::assertSame(PayException::ERROR_CONFIG, $caught->errorCode());
    }
}
