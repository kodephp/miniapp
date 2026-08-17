<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Union\Bridge\Fixtures\AlipaySigningFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 支付宝订阅（subscriptionCreatePlan / subscriptionSubscribe）桥接 e2e。
 *
 * - subscriptionCreatePlan 在本地生成计划（不触网），验证桥接正确委托 createPlan。
 * - subscriptionSubscribe 经 createSubscription 生成 `alipay.user.agreement.page.sign` 签约跳转
 *   URL，URL 查询串由网关同源 Signer::rsa2 签名；本测试用同一商户 RSA 公钥
 *   `openssl_verify(SHA256)===1` 独立复核签约 URL 签名，证明桥接在支付宝侧正确完成签约签名。
 */
class PaysBridgeAlipaySubscriptionSignChainTest extends TestCase
{
    private AlipaySigningFakeHttpClient $fake;

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

        // 订阅签约返回跳转 URL（不触网 POST），假客户端仅占位不影响断言
        $this->fake = new AlipaySigningFakeHttpClient('alipay_user_agreement_page_sign_response', [
            'code' => '10000',
            'msg'  => 'Success',
        ]);
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

    public function testAlipaySubscriptionPlanGeneratedLocally(): void
    {
        $plan = $this->adapter()->subscriptionCreatePlan([
            'name'     => '月度会员',
            'amount'   => 29.90,
            'currency' => 'CNY',
            'interval' => 'MONTH',
        ]);

        self::assertStringStartsWith('alipay_plan_', $plan['plan_id'] ?? '', '本地计划须生成 plan_id');
        self::assertSame('月度会员', $plan['name']);
        self::assertSame('CNY', $plan['currency']);
        self::assertArrayHasKey('period_rule_params', $plan, '计划须携带周期规则');
    }

    public function testAlipaySubscriptionSignUrlBuiltAndSigned(): void
    {
        $result = $this->adapter()->subscriptionSubscribe([
            'customer_id'  => 'EXT_AGR_20260817_001',
            'plan_id'      => 'alipay_plan_demo',
            'amount'       => 29.90,
            'interval'     => 'MONTH',
            'notify_url'   => 'https://example.com/sub/notify',
        ]);

        self::assertSame('GET', $result['method'], '签约返回可跳转 URL，HTTP 方法应为 GET');
        self::assertArrayHasKey('url', $result);
        self::assertSame('EXT_AGR_20260817_001', $result['external_agreement_no']);

        $rawQuery = (string) parse_url((string) $result['url'], PHP_URL_QUERY);
        parse_str($rawQuery, $parsed);
        /** @var array<string, string> $query */
        $query = $parsed;

        self::assertSame('alipay.user.agreement.page.sign', $query['method'] ?? '', '签约接口 method 必须为 alipay.user.agreement.page.sign');
        self::assertNotEmpty($query['biz_content'] ?? '', '签约 URL 必须携带 biz_content');
        self::assertNotEmpty($query['sign'] ?? '', '签约 URL 必须带 RSA2 签名');

        /** @var array<string, mixed> $signedBiz */
        $signedBiz = (array) json_decode($query['biz_content'], true);
        self::assertSame('EXT_AGR_20260817_001', $signedBiz['external_agreement_no'] ?? '', '商户侧协议号必须进入签约 biz_content');

        // 签名复核：重建网关同源 Signer::buildQueryString 基串，用商户公钥验签
        $sign = $query['sign'];
        unset($query['sign']);
        $plain = Signer::buildQueryString($query);
        $ok    = openssl_verify($plain, (string) base64_decode($sign), $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, '支付宝签约 URL 必须用同一商户公钥 RSA2 验签通过');
    }

    public function testAlipaySupportsSubscriptionTrue(): void
    {
        self::assertTrue(
            $this->adapter()->supportsSubscription(),
            '支付宝 AlipayGateway 实现了 createPlan/createSubscription，能力发现应返回 true',
        );
    }
}
