<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Union\Bridge\Fixtures\AlipayBalanceFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 支付宝余额查询真实网关签名链 + 响应解析 e2e（跨渠道差异验证）。
 *
 * 与 v2.0.24 的微信余额「明确不支持（抛 RuntimeException）」形成对照：
 * 支付宝 AlipayGateway 实现了 queryBalance，故 balanceQuery 在支付宝渠道上
 * 真实路由到网关方法、以商户私钥做 RSA2 签名、并解析响应为「分」单位。
 *
 * 验签基串复用网关同源的 Signer::buildQueryString（ksort + 排除 sign + 排除空值），
 * 与 Signer::rsa2 完全对称，是最强「非桩」证据。
 */
class PaysBridgeAlipayBalanceQueryTest extends TestCase
{
    private AlipayBalanceFakeHttpClient $fake;

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

        $this->fake = new AlipayBalanceFakeHttpClient();
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    private function adapter(): PaysBridgePayAdapter
    {
        return PaysBridge::adapter(Channel::AlipayMini, fn () => [
            'app_id'      => 'alipay_app',
            'private_key' => $this->privateKeyPem,
            'public_key'  => $this->publicKeyPem,
            'notify_url'  => 'https://example.com/notify',
        ]);
    }

    public function testAlipayBalanceQuerySignsAndParsesViaRealGateway(): void
    {
        $result = $this->adapter()->balanceQuery(['account_type' => 'ACCTRANS_ACCOUNT']);

        // 响应解析：元 → 分换算
        self::assertSame('ACCTRANS_ACCOUNT', $result['account_type']);
        self::assertSame(10000, $result['available_amount'], '100.00 元应换算为 10000 分');
        self::assertSame(1000, $result['freeze_amount'], '10.00 元应换算为 1000 分');
        self::assertSame(11000, $result['total_amount'], '110.00 元应换算为 11000 分');
        self::assertSame('CNY', $result['currency']);

        // 出站请求：方法 + 业务参数
        self::assertNotNull($this->fake->lastData, '必须发出真实 POST 请求');
        $data = $this->fake->lastData;
        self::assertSame('alipay.fund.account.query', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);
        self::assertArrayHasKey('biz_content', $data);
        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('ACCTRANS_ACCOUNT', $biz['account_type']);

        // 签名复核：复用网关同源 Signer::buildQueryString 重算基串，用商户公钥验签
        self::assertNotEmpty($data['sign'], '出站请求必须带 RSA2 签名');
        $sign = (string) $data['sign'];
        unset($data['sign']);
        $plain = Signer::buildQueryString($data);
        $ok    = openssl_verify($plain, (string) base64_decode($sign), $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, '支付宝余额查询请求必须用同一商户公钥 RSA2 验签通过');
    }

    public function testAlipaySupportsBalanceTrue(): void
    {
        self::assertTrue(
            $this->adapter()->supportsBalance(),
            '支付宝 AlipayGateway 实现了 queryBalance，能力发现应返回 true',
        );
    }
}
