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
 * 支付宝红包（redPacketSend）真实网关 RSA2 签名链 + 响应解析 e2e。
 *
 * 红包走 `alipay.fund.coupon.order.app.pay` + RSA2 签名。本测试用运行时生成的同一商户
 * RSA 公钥 `openssl_verify(SHA256)===1` 独立复核出站签名，证明桥接在支付宝侧正确完成
 * 红包高级能力签名（收款方 re_openid 须进入 payee_user_id）。
 */
class PaysBridgeAlipayRedPacketSignChainTest extends TestCase
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

        $this->fake = new AlipaySigningFakeHttpClient('alipay_fund_coupon_order_app_pay_response', [
            'code'         => '10000',
            'msg'          => 'Success',
            'out_order_no' => 'RP_20260817_001',
            'order_id'     => '2026081722001501',
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

    public function testAlipayRedPacketSignsAndParsesViaRealGateway(): void
    {
        $result = $this->adapter()->redPacketSend([
            'mch_billno'  => 'RP_20260817_001',
            'send_name'   => '示例商户',
            're_openid'   => '2088123456789012',
            'total_amount' => 500,
            'wishing'     => '恭喜发财',
            'act_name'    => '开业红包',
            'remark'      => '开业大吉',
        ]);

        // 响应解析：parseResponse 取首 key 为响应体
        self::assertSame('10000', $result['code']);
        self::assertSame('RP_20260817_001', $result['out_order_no']);
        self::assertSame('2026081722001501', $result['order_id']);

        // 出站请求：方法 + 业务参数
        self::assertNotNull($this->fake->lastData, '必须发出真实 POST 请求');
        $data = $this->fake->lastData;
        self::assertSame('alipay.fund.coupon.order.app.pay', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);
        self::assertArrayHasKey('biz_content', $data);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('RP_20260817_001', $biz['out_order_no']);
        self::assertSame('5.00', $biz['amount'], '金额以分为单位须转为元（500→5.00）');
        self::assertSame('2088123456789012', $biz['payee_user_id'], '红包收款方（re_openid）必须进入 payee_user_id');

        // 签名复核：复用网关同源 Signer::buildQueryString 重算基串，用商户公钥验签
        self::assertNotEmpty($data['sign'], '出站请求必须带 RSA2 签名');
        $sign = (string) $data['sign'];
        unset($data['sign']);
        $plain = Signer::buildQueryString($data);
        $ok    = openssl_verify($plain, (string) base64_decode($sign), $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, '支付宝红包请求必须用同一商户公钥 RSA2 验签通过');
    }

    public function testAlipaySupportsRedPacketTrue(): void
    {
        self::assertTrue(
            $this->adapter()->supportsRedPacket(),
            '支付宝 AlipayGateway 实现了 sendRedPacket，能力发现应返回 true',
        );
    }
}
