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
 * 支付宝单笔转账（transferSingle）真实网关 RSA2 签名链 + 响应解析 e2e。
 *
 * 与微信转账（transferSingle 经微信企业付款 V3 走 RSA/平台证书）/支付宝分账（alipay.trade.order.settle）
 * 形成跨渠道签名对照：支付宝转账走 `alipay.fund.trans.uni.transfer` + RSA2 签名，
 * 本测试用运行时生成的同一商户 RSA 公钥 `openssl_verify(SHA256)===1` 独立复核出站签名，
 * 证明桥接在支付宝侧正确完成转账高级能力签名。
 */
class PaysBridgeAlipayTransferSignChainTest extends TestCase
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

        $this->fake = new AlipaySigningFakeHttpClient('alipay_fund_trans_uni_transfer_response', [
            'code'       => '10000',
            'msg'        => 'Success',
            'out_biz_no' => 'T_20260817_001',
            'order_id'   => '2026081722001401',
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

    public function testAlipayTransferSignsAndParsesViaRealGateway(): void
    {
        $result = $this->adapter()->transferSingle([
            'out_biz_no' => 'T_20260817_001',
            'amount'     => 100,
            'description' => '技术服务费',
            'recipient'  => [
                'type'    => 'ALIPAY_USER_ID',
                'account' => '2088123456789012',
                'name'    => '张三',
            ],
        ]);

        // 响应解析：parseResponse 取首 key 为响应体
        self::assertSame('10000', $result['code']);
        self::assertSame('T_20260817_001', $result['out_biz_no']);
        self::assertSame('2026081722001401', $result['order_id']);

        // 出站请求：方法 + 业务参数
        self::assertNotNull($this->fake->lastData, '必须发出真实 POST 请求');
        $data = $this->fake->lastData;
        self::assertSame('alipay.fund.trans.uni.transfer', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);
        self::assertArrayHasKey('biz_content', $data);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('T_20260817_001', $biz['out_biz_no']);
        self::assertSame('1.00', $biz['trans_amount'], '金额以分为单位须转为元（100→1.00）');
        self::assertSame('DIRECT_TRANSFER', $biz['biz_scene']);
        $payee = (array) $biz['payee_info'];
        self::assertSame('2088123456789012', $payee['identity'], '收款方账号必须原样进入 biz_content');

        // 签名复核：复用网关同源 Signer::buildQueryString 重算基串，用商户公钥验签
        self::assertNotEmpty($data['sign'], '出站请求必须带 RSA2 签名');
        $sign = (string) $data['sign'];
        unset($data['sign']);
        $plain = Signer::buildQueryString($data);
        $ok    = openssl_verify($plain, (string) base64_decode($sign), $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, '支付宝转账请求必须用同一商户公钥 RSA2 验签通过');
    }

    public function testAlipaySupportsTransferTrue(): void
    {
        self::assertTrue(
            $this->adapter()->supportsTransfer(),
            '支付宝 AlipayGateway 实现了 singleTransfer，能力发现应返回 true',
        );
    }
}
