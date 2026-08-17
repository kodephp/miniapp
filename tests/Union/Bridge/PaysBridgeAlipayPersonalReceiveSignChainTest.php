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
 * 支付宝个人收款（PersonalReceiveCapableInterface）端到端（RSA2 签名拼装全链路）验证。
 *
 * 与微信侧的 {@see \Kode\MiniApp\Tests\Union\Bridge\PaysBridgePersonalReceiveSignChainTest}
 * 形成跨渠道对照：微信走「XML + MD5」、支付宝走「表单 + RSA2」，二者共用同一 PaysBridge 桥接入口。
 *
 * 本测试经真实 AlipayGateway + AlipaySigningFakeHttpClient 走通「参数校验 / RSA2 签名 /
 * 报文拼装 / 响应解析」真实代码路径而不触网，覆盖个人收款四个方法各自的出站端点：
 *  - createQrCode      → alipay.trade.precreate（当面付扫码，返回 qr_code）
 *  - queryRecords      → alipay.trade.query（收款记录查询）
 *  - withdraw          → alipay.fund.trans.uni.transfer（提现到银行卡）
 *  - queryWithdraw     → alipay.fund.trans.common.query（提现结果查询）
 *
 * 断言核心：真实网关对出站请求做了 RSA2 签名（Signer::rsa2），且签名可用同一商户公钥
 * 经 openssl_verify(SHA256) 重新校验通过——即「桥接只做路由，签名由真实 kode/pays 网关完成」的全链路证据。
 */
final class PaysBridgeAlipayPersonalReceiveSignChainTest extends TestCase
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
        self::assertNotFalse($res, 'openssl 必须可用以生成测试 RSA 密钥对');

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

    public function testCreateQrCodeSignsPrecreateViaRealGateway(): void
    {
        $fake = $this->fake('alipay_trade_precreate_response', [
            'code'    => '10000',
            'msg'     => 'Success',
            'qr_code' => 'https://qr.alipay.com/2088xx',
            'out_trade_no' => 'PERSONAL_2026081700001',
        ]);

        $result = $this->adapter()->personalReceiveCreateQrCode([
            'amount'      => 100,
            'description' => '个人收款测试',
        ]);

        // 网关返回结构：out_trade_no（PERSONAL_ 前缀自动生成）/ qr_code / amount / description
        self::assertStringStartsWith('PERSONAL_', $result['out_trade_no'] ?? '');
        self::assertSame('https://qr.alipay.com/2088xx', $result['qr_code'] ?? '');
        self::assertSame(100, $result['amount'] ?? null);
        self::assertSame('个人收款测试', $result['description'] ?? '');

        self::assertNotNull($fake->lastData, '必须发出真实 POST 请求');
        $data = $fake->lastData;
        self::assertSame('alipay.trade.precreate', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        // 网关把以「分」为单位的 amount 转换为「元」：100 分 → 1.00 元
        self::assertSame('1.00', $biz['total_amount']);
        self::assertSame('个人收款测试', $biz['subject']);

        $this->assertRsa2Verified($data);
    }

    public function testQueryRecordsSignsTradeQueryViaRealGateway(): void
    {
        $fake = $this->fake('alipay_trade_query_response', [
            'code'         => '10000',
            'msg'          => 'Success',
            'out_trade_no' => 'PERSONAL_2026081700002',
            'trade_status' => 'TRADE_SUCCESS',
        ]);

        $result = $this->adapter()->personalReceiveQueryRecords([
            'start_time' => '2026-08-16 00:00:00',
            'end_time'   => '2026-08-17 00:00:00',
        ]);

        self::assertSame('10000', $result['code'] ?? null);
        self::assertSame('TRADE_SUCCESS', $result['trade_status'] ?? null);

        self::assertNotNull($fake->lastData, '必须发出真实 POST 请求');
        $data = $fake->lastData;
        self::assertSame('alipay.trade.query', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('2026-08-16 00:00:00', $biz['start_time'] ?? null);

        $this->assertRsa2Verified($data);
    }

    public function testWithdrawSignsUniTransferViaRealGateway(): void
    {
        $fake = $this->fake('alipay_fund_trans_uni_transfer_response', [
            'code'      => '10000',
            'msg'       => 'Success',
            'out_biz_no' => 'WD_20260817_001',
            'status'    => 'SUCCESS',
        ]);

        $result = $this->adapter()->personalReceiveWithdraw([
            'out_biz_no'   => 'WD_20260817_001',
            'amount'       => 5000,
            'bank_card_no' => '6228480402564890018',
            'real_name'    => '张三',
        ]);

        self::assertSame('10000', $result['code'] ?? null);
        self::assertSame('WD_20260817_001', $result['out_biz_no'] ?? null);

        self::assertNotNull($fake->lastData, '必须发出真实 POST 请求');
        $data = $fake->lastData;
        self::assertSame('alipay.fund.trans.uni.transfer', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('WD_20260817_001', $biz['out_biz_no']);
        self::assertSame('50.00', $biz['trans_amount']);
        self::assertSame('TRANS_BANKCARD_NO_PWD', $biz['product_code']);
        self::assertSame('张三', $biz['payee_info']['name'] ?? null);

        $this->assertRsa2Verified($data);
    }

    public function testQueryWithdrawSignsCommonQueryViaRealGateway(): void
    {
        $fake = $this->fake('alipay_fund_trans_common_query_response', [
            'code'      => '10000',
            'msg'       => 'Success',
            'out_biz_no' => 'WD_20260817_001',
            'status'    => 'SUCCESS',
        ]);

        $result = $this->adapter()->personalReceiveQueryWithdraw('WD_20260817_001');

        self::assertSame('10000', $result['code'] ?? null);
        self::assertSame('WD_20260817_001', $result['out_biz_no'] ?? null);

        self::assertNotNull($fake->lastData, '必须发出真实 POST 请求');
        $data = $fake->lastData;
        self::assertSame('alipay.fund.trans.common.query', $data['method']);
        self::assertSame('RSA2', $data['sign_type']);

        $biz = (array) json_decode((string) $data['biz_content'], true);
        self::assertSame('WD_20260817_001', $biz['out_biz_no']);

        $this->assertRsa2Verified($data);
    }

    public function testAlipaySupportsPersonalReceiveTrue(): void
    {
        self::assertTrue(
            $this->adapter()->supportsPersonalReceive(),
            '支付宝 AlipayGateway 实现了 createQrCode，个人收款能力发现应返回 true',
        );
    }
}
