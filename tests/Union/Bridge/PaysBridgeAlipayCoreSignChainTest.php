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
 * 支付宝核心下单生命周期（createOrder / queryOrder / refund / queryRefund / closeOrder）
 * 真实网关 RSA2 签名链 + 响应解析 e2e。
 *
 * 与微信 V2「XML + MD5 签名」侧（PaysBridgeCreateOrderSignChainTest /
 * PaysBridgeQuerySignChainTest / PaysBridgeRefundSignChainTest）形成跨渠道签名对照：
 * 微信走 MD5、支付宝走 RSA2，二者共用同一套 PaysBridge 桥接入口。
 *
 * 覆盖要点：
 *  - createOrder 走支付宝「页面支付」路径，返回 GET 跳转 URL（含 query 串签名），
 *    用商户公钥独立复核 query 串的 RSA2 签名；
 *  - queryOrder / refund / queryRefund / closeOrder 走 POST，由 AlipaySigningFakeHttpClient
 *    拦截并记录 lastData，复核出站 RSA2 签名与 biz_content 业务参数；
 *  - 响应经 parseResponse 取首 key（alipay_trade_xxx_response）还原业务数据。
 */
class PaysBridgeAlipayCoreSignChainTest extends TestCase
{
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
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    /**
     * 构造支付宝签名链假客户端并注册到 Pay 门面（按方法返回对应响应包裹）
     *
     * @param array<string, mixed> $responseBody 成功响应体的业务字段
     */
    private function fake(string $responseKey, array $responseBody): AlipaySigningFakeHttpClient
    {
        $fake = new AlipaySigningFakeHttpClient($responseKey, $responseBody);
        Pay::setHttpClient($fake);
        Pay::clearCache();

        return $fake;
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

    /**
     * 用同一商户公钥独立复核出站参数的 RSA2 签名（与网关 Signer::rsa2 同源基串）
     *
     * @param array<int|string, mixed> $params 含 sign 字段的出站参数
     */
    private function assertRsa2Verified(array $params): void
    {
        self::assertNotEmpty($params['sign'] ?? null, '出站请求必须带 RSA2 签名');
        $sign  = (string) $params['sign'];
        $plain = Signer::buildQueryString($params); // 默认排除 sign 字段
        $ok    = openssl_verify($plain, (string) base64_decode($sign), $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, '支付宝请求必须用同一商户公钥 RSA2 验签通过');
    }

    public function testCreateOrderSignsGetRedirectUrlViaRealGateway(): void
    {
        // createOrder 走页面支付，返回 GET 跳转 URL（不触 POST）
        $this->fake('alipay_trade_query_response', ['code' => '10000', 'msg' => 'Success']);

        $result = $this->adapter()->createOrder([
            'out_trade_no' => 'A_O_001',
            'total_amount' => '1.00',
            'subject'      => '技术服务费',
        ]);

        self::assertSame('GET', $result['method'] ?? null);
        self::assertNotEmpty($result['url'] ?? null, '页面支付必须返回跳转 URL');

        // 解析 GET URL 的 query 串（签名在此处）
        $query = (string) parse_url((string) $result['url'], PHP_URL_QUERY);
        parse_str($query, $data);

        self::assertSame('alipay.trade.page.pay', $data['method'] ?? null);
        self::assertSame('RSA2', $data['sign_type'] ?? null);
        self::assertArrayHasKey('biz_content', $data);

        $bizContent = is_string($data['biz_content'] ?? null) ? (string) $data['biz_content'] : '';
        $biz = (array) json_decode($bizContent, true);
        self::assertSame('A_O_001', $biz['out_trade_no']);
        self::assertSame('1.00', $biz['total_amount']);
        self::assertSame('技术服务费', $biz['subject']);

        $this->assertRsa2Verified($data);
    }

    public function testQueryOrderSignsOutboundRequestViaRealGateway(): void
    {
        $fake = $this->fake('alipay_trade_query_response', [
            'code'         => '10000',
            'msg'          => 'Success',
            'out_trade_no' => 'A_Q_001',
            'trade_status' => 'TRADE_SUCCESS',
        ]);

        $result = $this->adapter()->queryOrder('A_Q_001');

        // 响应解析：parseResponse 取首 key 为响应体
        self::assertSame('10000', $result['code']);
        self::assertSame('TRADE_SUCCESS', $result['trade_status']);

        self::assertNotNull($fake->lastData, '必须发出真实 POST 请求');
        $data = $fake->lastData;
        self::assertSame('alipay.trade.query', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('A_Q_001', $biz['out_trade_no']);

        $this->assertRsa2Verified($data);
    }

    public function testRefundSignsOutboundRequestViaRealGateway(): void
    {
        $fake = $this->fake('alipay_trade_refund_response', [
            'code'         => '10000',
            'msg'          => 'Success',
            'out_trade_no' => 'A_R_001',
            'refund_fee'   => '1.00',
        ]);

        $result = $this->adapter()->refund([
            'out_trade_no'   => 'A_R_001',
            'refund_amount'  => '1.00',
            'refund_reason'  => '用户申请退款',
        ]);

        self::assertSame('10000', $result['code']);
        self::assertSame('1.00', $result['refund_fee']);

        self::assertNotNull($fake->lastData, '必须发出真实 POST 请求');
        $data = $fake->lastData;
        self::assertSame('alipay.trade.refund', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('A_R_001', $biz['out_trade_no']);
        self::assertSame('1.00', $biz['refund_amount']);
        self::assertSame('用户申请退款', $biz['refund_reason']);

        self::assertSame('A_R_001', $result['out_trade_no']);

        $this->assertRsa2Verified($data);
    }

    public function testQueryRefundSignsOutboundRequestViaRealGateway(): void
    {
        $fake = $this->fake('alipay_trade_fastpay_refund_query_response', [
            'code'          => '10000',
            'msg'           => 'Success',
            'out_request_no' => 'A_QR_001',
            'refund_amount' => '1.00',
        ]);

        $result = $this->adapter()->queryRefund('A_QR_001');

        self::assertSame('10000', $result['code']);
        self::assertSame('1.00', $result['refund_amount']);

        self::assertNotNull($fake->lastData, '必须发出真实 POST 请求');
        $data = $fake->lastData;
        self::assertSame('alipay.trade.fastpay.refund.query', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('A_QR_001', $biz['out_request_no']);

        $this->assertRsa2Verified($data);
    }

    public function testCloseOrderSignsOutboundRequestViaRealGateway(): void
    {
        $fake = $this->fake('alipay_trade_close_response', [
            'code'         => '10000',
            'msg'          => 'Success',
            'out_trade_no' => 'A_C_001',
        ]);

        $result = $this->adapter()->closeOrder('A_C_001');

        self::assertSame('10000', $result['code']);
        self::assertSame('A_C_001', $result['out_trade_no']);

        self::assertNotNull($fake->lastData, '必须发出真实 POST 请求');
        $data = $fake->lastData;
        self::assertSame('alipay.trade.close', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('A_C_001', $biz['out_trade_no']);

        $this->assertRsa2Verified($data);
    }

    public function testAlipaySupportsRefundTrue(): void
    {
        self::assertTrue(
            $this->adapter()->supportsRefund(),
            '支付宝 AlipayGateway 实现了 refund（applyRefund），能力发现应返回 true',
        );
    }
}
