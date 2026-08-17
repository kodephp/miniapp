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
 * 支付宝分账（createProfitSharing）真实网关 RSA2 签名链 + 响应解析 e2e。
 *
 * 与微信分账（profitSharingCreate 经 WechatPayGateway V2 走 MD5）形成跨渠道签名对照：
 * 支付宝 AlipayGateway 的分账走 `alipay.trade.order.settle` + RSA2 签名，
 * 本测试用运行时生成的同一商户 RSA 公钥 `openssl_verify(SHA256)===1` 独立复核出站签名
 * （验签基串复用网关同源 Signer::buildQueryString），证明桥接在支付宝侧正确完成高级能力签名。
 */
class PaysBridgeAlipayProfitSharingSignChainTest extends TestCase
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

        $this->fake = new AlipaySigningFakeHttpClient('alipay_trade_order_settle_response', [
            'code'          => '10000',
            'msg'           => 'Success',
            'trade_no'      => '2026081722001001',
            'out_request_no' => 'PS_20260817_001',
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

    public function testAlipayProfitSharingSignsAndParsesViaRealGateway(): void
    {
        $result = $this->adapter()->profitSharingCreate([
            'out_order_no'  => 'PS_20260817_001',
            'transaction_id' => '2026081722001001',
            'receivers'     => [
                [
                    'trans_in_type' => 'userId',
                    'trans_in'      => '2088123456789012',
                    'amount'        => 1.00,
                    'desc'          => '分账给服务商',
                ],
            ],
        ]);

        // 响应解析：parseResponse 取首 key 为响应体
        self::assertSame('10000', $result['code']);
        self::assertSame('PS_20260817_001', $result['out_request_no']);
        self::assertSame('2026081722001001', $result['trade_no']);

        // 出站请求：方法 + 业务参数
        self::assertNotNull($this->fake->lastData, '必须发出真实 POST 请求');
        $data = $this->fake->lastData;
        self::assertSame('alipay.trade.order.settle', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);
        self::assertArrayHasKey('biz_content', $data);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('PS_20260817_001', $biz['out_request_no']);
        self::assertSame('2026081722001001', $biz['trade_no']);
        self::assertArrayHasKey('royalty_parameters', $biz);
        $royalty = (array) $biz['royalty_parameters'][0];
        self::assertSame('2088123456789012', $royalty['trans_in'], '分账收款方必须原样进入 biz_content');

        // 签名复核：复用网关同源 Signer::buildQueryString 重算基串，用商户公钥验签
        self::assertNotEmpty($data['sign'], '出站请求必须带 RSA2 签名');
        $sign = (string) $data['sign'];
        unset($data['sign']);
        $plain = Signer::buildQueryString($data);
        $ok    = openssl_verify($plain, (string) base64_decode($sign), $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, '支付宝分账请求必须用同一商户公钥 RSA2 验签通过');
    }

    public function testAlipaySupportsProfitSharingTrue(): void
    {
        self::assertTrue(
            $this->adapter()->supportsProfitSharing(),
            '支付宝 AlipayGateway 实现了 createProfitSharing，能力发现应返回 true',
        );
    }
}
