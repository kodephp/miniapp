<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Utils\Xml;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 对账能力真实网关签名链 e2e（补强核心支付能力签名链覆盖率）。
 *
 * 与 v2.0.19 个人收款「queryRecords → pay/downloadbill」签名链（彼时仅证个人收款入口）不同，
 * 本测试针对「核心 PayAdapter 对账能力」reconciliationDownloadBill / reconciliationDownloadFundFlow：
 * 两者均经微信 V2「XML + MD5(api_key)」规范出站，但此前只有委托冒烟（仅断言返回结构），
 * 从未验证**出站请求被真实 MD5 签名且可被 api_key 独立重算校验**、以及端点正确。
 *
 * 全程不触网：沿用既有 FakePaysHttpClient（捕获出站 URL / 原始 XML 请求体），
 * 用与网关完全一致的 Signer::md5 规则对出站报文重算并 verifyMd5 复核（非桩）。
 */
final class PaysBridgeReconciliationSignChainTest extends TestCase
{
    private const API_KEY = 'unit_test_api_key_0123456789';

    private FakePaysHttpClient $fake;

    private string $privateKeyPem;

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

        $this->fake = new FakePaysHttpClient();
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    public function testDownloadBillIsSignedV2Md5AndRoutedToDownloadbill(): void
    {
        $adapter = $this->adapter();

        /** @var array<string, mixed> $result */
        $result = $adapter->reconciliationDownloadBill(['bill_date' => '20260814']);

        // 端点必须是微信对账单下载接口
        self::assertStringContainsString(
            'pay/downloadbill',
            (string) $this->fake->lastUrl,
            '对账下载必须路由到 pay/downloadbill 端点',
        );

        $this->assertOutboundSignVerifiable((string) $this->fake->lastRawBody);

        // 网关把响应原始文本（此处为 fake 的 XML）包进 bill_date / records 结构返回
        self::assertSame('20260814', $result['bill_date']);
        self::assertIsArray($result['records']);
    }

    public function testDownloadFundFlowIsSignedV2Md5AndRoutedToDownloadfundflow(): void
    {
        $adapter = $this->adapter();

        /** @var array<string, mixed> $result */
        $result = $adapter->reconciliationDownloadFundFlow([
            'bill_date'    => '20260814',
            'account_type' => 'Basic',
        ]);

        self::assertStringContainsString(
            'pay/downloadfundflow',
            (string) $this->fake->lastUrl,
            '资金账单下载必须路由到 pay/downloadfundflow 端点',
        );

        $this->assertOutboundSignVerifiable((string) $this->fake->lastRawBody);

        self::assertSame('20260814', $result['bill_date']);
        self::assertSame('Basic', $result['account_type']);
        self::assertIsArray($result['records']);
    }

    /**
     * 出站 XML 报文必须携带可用 api_key 独立重算校验通过的 MD5 签名（与网关 Signer::md5 完全对称）。
     */
    private function assertOutboundSignVerifiable(string $xml): void
    {
        $req = $this->parseXml($xml);

        self::assertArrayHasKey('sign', $req, '真实网关必须对出站对账请求做了 MD5 签名');

        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站对账请求签名必须可用 api_key 重新校验通过',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseXml(string $xml): array
    {
        $arr = Xml::parse($xml);

        self::assertNotEmpty($arr, '出站请求体必须能解析为字段数组');

        return $arr;
    }

    private function adapter(): PaysBridgePayAdapter
    {
        return PaysBridge::adapter(Channel::WechatMini, fn () => [
            'app_id'      => 'wx_app',
            'mch_id'      => 'mch_1',
            'api_key'     => self::API_KEY,
            'serial_no'   => 'TEST_SERIAL_NO_001',
            'private_key' => $this->privateKeyPem,
        ]);
    }
}
