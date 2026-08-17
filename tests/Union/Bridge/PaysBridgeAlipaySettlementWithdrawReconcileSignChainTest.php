<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\AlipaySigningFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 支付宝结算 / 提现 / 对账能力真实网关 RSA2 签名链 e2e（补强核心能力跨渠道对称性）。
 *
 * 微信侧的结算（settleToWallet/settleToBankCard/settleToPayout）、对账（downloadBill/downloadFundFlow）
 * 已由 PaysBridgeSettlementSignChainTest / PaysBridgeReconciliationSignChainTest 以 MD5 覆盖；本测试补齐
 * 支付宝侧的同一组能力，均经真实 AlipayGateway + FakePaysHttpClient 走通「RSA2 签名 / 报文拼装 / 响应解析」
 * 真实代码路径而不触网，用同一商户公钥 openssl_verify(SHA256)===1 独立复核出站签名。
 *
 * 覆盖：
 *  - settlementToWallet  → 委托 singleTransfer → alipay.fund.trans.uni.transfer
 *  - settlementToBankCard → 委托 withdraw       → alipay.fund.trans.uni.transfer（trans_amount = amount/100）
 *  - settlementQuery     → 委托 queryTransfer   → alipay.fund.trans.common.query
 *  - reconciliationDownloadBill    → alipay.data.dataservice.bill.downloadurl.query
 *  - reconciliationDownloadFundFlow → alipay.data.bill.ereceipt.query（file_id 直查路径）
 *  - settlementToPayout   → 支付宝无外部账户 Payout 语义，网关层 methodNotSupported，归一为 ApiException
 *
 * 注：网关 withdraw / queryWithdraw 是内部委托实现，适配器未单独暴露，其出站签名已分别由
 * settlementToBankCard / settlementQuery 经真实网关间接走通并验签。
 */
final class PaysBridgeAlipaySettlementWithdrawReconcileSignChainTest extends TestCase
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

        // 所有方法仅出站一次 POST（下载类在缺少下载 URL 时提前返回，不触网 GET）。
        // 响应体不含 bill_download_url / status=SUCCESS / download_url，确保不触发二次下载。
        $this->fake = new AlipaySigningFakeHttpClient('alipay_fund_trans_uni_transfer_response', [
            'code'      => '10000',
            'msg'       => 'Success',
            'out_biz_no' => 'WBZ_TEST_001',
            'order_id'  => 'ALI_ORDER_1',
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

    /**
     * 重建网关同源 Signer::buildQueryString 基串，用商户公钥独立复核出站 RSA2 签名。
     *
     * @param array<string, mixed> $data
     */
    private function assertOutboundRsa2Verified(array $data, string $label): void
    {
        $sign = (string) ($data['sign'] ?? '');
        self::assertNotEmpty($sign, "{$label} 出站请求必须携带 RSA2 签名");

        $payload = $data;
        unset($payload['sign']);
        $plain = Signer::buildQueryString($payload);

        $ok = openssl_verify($plain, (string) base64_decode($sign), $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, "{$label} 出站请求必须用同一商户公钥 RSA2 验签通过");
    }

    /**
     * @return array<string, mixed>
     */
    private function lastBizContent(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        /** @var array<string, mixed> $biz */
        $biz = is_array(json_decode((string) ($data['biz_content'] ?? ''), true))
            ? json_decode((string) ($data['biz_content'] ?? ''), true)
            : [];

        return $biz;
    }

    public function testSettleToWalletSignsViaRealGateway(): void
    {
        $result = $this->adapter()->settlementToWallet([
            'out_biz_no'   => 'SET_W_001',
            'amount'       => 10000,
            'account'      => '2088123456789012',
            'real_name'    => '张三',
        ]);

        self::assertSame('10000', $result['code'] ?? null);

        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        self::assertSame('alipay.fund.trans.uni.transfer', $data['method'] ?? '', '结算到钱包 method 必须为 alipay.fund.trans.uni.transfer');

        $biz = $this->lastBizContent();
        self::assertSame('SET_W_001', $biz['out_biz_no'] ?? '', 'biz_content 必须携带 out_biz_no');
        /** @var array<string, mixed> $payee */
        $payee = (array) ($biz['payee_info'] ?? []);
        self::assertSame('2088123456789012', $payee['identity'] ?? '', '结算收款方必须为 account');

        $this->assertOutboundRsa2Verified($data, '结算到钱包');
    }

    public function testSettleToBankCardSignsViaRealGateway(): void
    {
        $result = $this->adapter()->settlementToBankCard([
            'out_biz_no'   => 'SET_B_001',
            'amount'       => 5000,
            'bank_card_no' => '6222021234567890',
            'real_name'    => '李四',
        ]);

        self::assertSame('10000', $result['code'] ?? null);

        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        self::assertSame('alipay.fund.trans.uni.transfer', $data['method'] ?? '', '结算到银行卡 method 必须为 alipay.fund.trans.uni.transfer');

        $biz = $this->lastBizContent();
        /** @var array<string, mixed> $payee */
        $payee = (array) ($biz['payee_info'] ?? []);
        self::assertSame('6222021234567890', $payee['identity'] ?? '', '结算收款方必须为 bank_card_no');

        $this->assertOutboundRsa2Verified($data, '结算到银行卡');
    }

    public function testSettlementQuerySignsViaRealGateway(): void    {
        $result = $this->adapter()->settlementQuery('SET_W_001');

        self::assertSame('10000', $result['code'] ?? null);

        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        self::assertSame('alipay.fund.trans.common.query', $data['method'] ?? '', '结算查询 method 必须为 alipay.fund.trans.common.query');

        $biz = $this->lastBizContent();
        self::assertSame('SET_W_001', $biz['out_biz_no'] ?? '', '结算查询 biz_content 必须携带 out_biz_no');

        $this->assertOutboundRsa2Verified($data, '结算查询');
    }

    public function testReconciliationDownloadBillSignsViaRealGateway(): void
    {
        $result = $this->adapter()->reconciliationDownloadBill([
            'bill_date' => '2026-08-17',
            'bill_type' => 'trade',
        ]);

        // 假响应未含 bill_download_url，网关提前返回（不触网 GET 下载）
        self::assertSame('2026-08-17', $result['bill_date'] ?? null);
        self::assertSame('', $result['bill_download_url'] ?? null, '无下载 URL 时不应触发文件下载');

        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        self::assertSame('alipay.data.dataservice.bill.downloadurl.query', $data['method'] ?? '', '对账单下载 method 必须为 alipay.data.dataservice.bill.downloadurl.query');

        $biz = $this->lastBizContent();
        self::assertSame('2026-08-17', $biz['bill_date'] ?? '', '对账 biz_content 必须携带 bill_date');

        $this->assertOutboundRsa2Verified($data, '对账单下载');
    }

    public function testReconciliationDownloadFundFlowSignsViaRealGateway(): void
    {
        $result = $this->adapter()->reconciliationDownloadFundFlow([
            'file_id' => 'FID_001',
        ]);

        // file_id 直查路径：POST ereceipt.query，状态非 SUCCESS 不触发 GET 下载
        self::assertSame('FID_001', $result['file_id'] ?? null);

        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        self::assertSame('alipay.data.bill.ereceipt.query', $data['method'] ?? '', '资金账单 method 必须为 alipay.data.bill.ereceipt.query');

        $biz = $this->lastBizContent();
        self::assertSame('FID_001', $biz['file_id'] ?? '', '资金账单 biz_content 必须携带 file_id');

        $this->assertOutboundRsa2Verified($data, '资金账单');
    }

    public function testSettleToPayoutUnsupportedNormalizedToApiException(): void
    {
        // 支付宝无外部账户 Payout 语义：网关抛 methodNotSupported → 桥接 invokeGateway 归一为 ApiException
        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/settleToPayout/');

        $this->adapter()->settlementToPayout([
            'out_biz_no' => 'PAYOUT_001',
            'amount'     => 1000,
            'account'    => 'external_acct',
        ]);
    }
}
