<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 个人收款（PersonalReceiveCapableInterface）端到端（签名拼装全链路）验证。
 *
 * 与 {@see \Kode\MiniApp\Tests\Union\Bridge\PaysBridgeAdvancedTest::testPersonalReceiveDelegatesToGateway()}
 * 形成关键互补：原测试仅将 wechat 网关注册替换为「返回 _method 桩」的
 * {@see \Kode\MiniApp\Tests\Union\Bridge\Fixtures\PersonalReceiveCapableTestGateway}，只验证了「方法被正确转发」，
 * 却从未驱动**真实** {@see \Kode\Pays\Gateway\Wechat\WechatPayGateway}，因此个人收款出站请求的
 * 参数拼装 / MD5 签名 / 报文格式从未被真实代码路径证明。
 *
 * 本测试经真实 WechatPayGateway + FakePaysHttpClient 走通「参数校验 / 签名 / 报文拼装 / 响应解析」
 * 真实代码路径而不触网，覆盖个人收款四个方法各自的出站端点：
 *  - createQrCode      → pay/unifiedorder（NATIVE 扫码，标准 V2 下单）
 *  - withdraw          → mmpaymkttransfers/pay_bank（企业付款到银行卡，需证书）
 *  - queryWithdraw     → mmpaymkttransfers/query_bank（查询企业付款到银行卡，需证书）
 *  - queryRecords      → pay/downloadbill（对账单下载，返回 CSV 由网关本地解析）
 *
 * 断言核心：真实网关对出站请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 经
 * Signer::verifyMd5 重新校验通过——即「桥接只做路由，签名由真实 kode/pays 网关完成」的全链路证据。
 */
final class PaysBridgePersonalReceiveSignChainTest extends TestCase
{
    /**
     * 微信 V2 响应用于验签的 api_key（须与传给网关的 config.api_key 完全一致）
     */
    private const API_KEY = 'unit_test_api_key_0123456789';

    private FakePaysHttpClient $fake;

    protected function setUp(): void
    {
        $this->fake = new FakePaysHttpClient(self::API_KEY);
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    /**
     * @return array<string, mixed>
     */
    private function wechatConfig(): array
    {
        return ['app_id' => 'wx_app', 'mch_id' => 'mch_1', 'api_key' => self::API_KEY];
    }

    /**
     * 将微信 V2 出站 XML 解析为关联数组（剥离 CDATA，空元素归一为空字符串）。
     *
     * 逐节点显式 (string) 取文本，避免空元素被 json_encode(simplexml) 解析成空数组 []
     * 导致 Signer::verifyMd5 遍历时触发 "Array to string conversion"。
     *
     * @return array<string, string>
     */
    private function parseWechatXml(string $xml): array
    {
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);
        \assert($doc !== false);

        $arr = [];
        foreach ($doc->children() as $name => $node) {
            $arr[$name] = (string) $node;
        }

        return $arr;
    }

    public function testCreateQrCodeSignsOutboundUnifiedorderViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->personalReceiveCreateQrCode([
            'amount'      => 100,
            'description' => '个人收款测试',
        ]);

        // 真实网关解析成功响应（FakePaysHttpClient 返回的微信 V2 成功报文）：
        // createQrCode 返回自身结构（out_trade_no / code_url / prepay_id / amount / description），
        // 不暴露 return_code。prepay_id 直接来自网关解析出的响应，证明其走通了真实解析路径；
        // out_trade_no 以 PERSONAL_ 前缀由网关自动生成。
        self::assertStringStartsWith('PERSONAL_', $result['out_trade_no'] ?? '');
        self::assertSame('WXPREPAY_1', $result['prepay_id'] ?? '', 'prepay_id 须来自真实网关解析出的响应');
        self::assertSame(100, $result['amount'] ?? null);
        self::assertSame('个人收款测试', $result['description'] ?? '');

        // 出站端点必须是 pay/unifiedorder（NATIVE 扫码下单）
        self::assertStringContainsString('pay/unifiedorder', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 pay/unifiedorder 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        // 业务参数必须进入出站请求并参与签名
        self::assertSame('100', $req['total_fee'] ?? '');
        self::assertSame('个人收款测试', $req['body'] ?? '');
        self::assertSame('NATIVE', $req['trade_type'] ?? '');

        // 全链路核心：生成收款码请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站统一下单请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站统一下单请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testWithdrawSignsOutboundPayBankViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->personalReceiveWithdraw([
            'out_biz_no'   => 'WD_20260816_001',
            'amount'       => 5000,
            'bank_card_no' => '6228480402564890018',
            'real_name'    => '张三',
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 mmpaymkttransfers/pay_bank（企业付款到银行卡，需证书）
        self::assertStringContainsString('mmpaymkttransfers/pay_bank', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 mmpaymkttransfers/pay_bank 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('WD_20260816_001', $req['partner_trade_no'] ?? '');
        self::assertSame('5000', $req['amount'] ?? '');
        // 未配置 bank_public_key 时 encryptBankCard 退化为 base64，仍应产出非空密文
        self::assertNotEmpty($req['enc_bank_no'] ?? '');
        self::assertNotEmpty($req['enc_true_name'] ?? '');

        // 全链路核心：提现请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站提现请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站提现请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testQueryWithdrawSignsOutboundQueryBankViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->personalReceiveQueryWithdraw('WD_20260816_001');

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 mmpaymkttransfers/query_bank（查询企业付款到银行卡，需证书）
        self::assertStringContainsString('mmpaymkttransfers/query_bank', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 mmpaymkttransfers/query_bank 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('WD_20260816_001', $req['partner_trade_no'] ?? '');

        // 全链路核心：查询提现请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站查询提现请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站查询提现请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testQueryRecordsSignsOutboundDownloadbillViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->personalReceiveQueryRecords([
            'start_time' => '2026-08-16 00:00:00',
        ]);

        // 对账单下载接口返回含 bill_date 与解析后记录列表的结构（测试桩返回 XML 非 CSV，记录为空数组）
        self::assertArrayHasKey('bill_date', $result);
        self::assertIsArray($result['records'] ?? null);

        // 出站端点必须是 pay/downloadbill（对账单下载）
        self::assertStringContainsString('pay/downloadbill', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 pay/downloadbill 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('ALL', $req['bill_type'] ?? '');

        // 全链路核心：查询收款记录（对账单下载）请求同样做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站对账单下载请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站对账单下载请求签名必须可用 api_key 重新校验通过',
        );
    }
}
