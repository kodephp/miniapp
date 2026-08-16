<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 转账 / 红包（企业付款到零钱 + 现金红包 + 裂变红包）端到端（签名拼装全链路）验证。
 *
 * 与 {@see \Kode\MiniApp\Tests\Union\Bridge\PaysBridgeAdvancedTest} 形成关键互补：原测试仅断言
 * 「出站端点 + return_code」，从未用同一 api_key 重新校验真实网关生成的 MD5 签名——即「桥接只做
 * 路由，签名由真实 kode/pays 网关完成」这一全链路事实未被证明。
 *
 * 本测试经真实 WechatPayGateway + FakePaysHttpClient 走通「参数校验 / 签名 / 报文拼装 / 响应解析」
 * 真实代码路径而不触网，覆盖四个方法各自的出站端点（均为微信 V2 XML + MD5 签名，需证书但签名在
 * 证书附加之前完成，故离线可测）：
 *  - transferSingle  → mmpaymkttransfers/promotion/transfers（企业付款到零钱）
 *  - redPacketSend    → mmpaymkttransfers/sendredpack（普通现金红包）
 *  - redPacketGroup   → mmpaymkttransfers/sendgroupredpack（裂变红包 / 群红包）
 *  - redPacketQuery   → mmpaymkttransfers/gethbinfo（查询红包发放记录）
 *
 * 断言核心：真实网关对出站请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 经
 * Signer::verifyMd5 重新校验通过。附带一例「网关参数校验（PayException）经 invokeGateway 归一为
 * ApiException」的归一化证据。
 */
final class PaysBridgeTransferRedPacketSignChainTest extends TestCase
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

    public function testTransferSingleSignsOutboundPromotionTransfersViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->transferSingle([
            'out_biz_no' => 'T_20260816_001',
            'amount'     => 100,
            'recipient'  => ['type' => 'openid', 'account' => 'OPENID_XYZ', 'name' => '张三'],
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 mmpaymkttransfers/promotion/transfers（企业付款到零钱）
        self::assertStringContainsString('mmpaymkttransfers/promotion/transfers', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 mmpaymkttransfers/promotion/transfers 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        // 业务参数必须进入出站请求并参与签名
        self::assertSame('T_20260816_001', $req['partner_trade_no'] ?? '');
        self::assertSame('OPENID_XYZ', $req['openid'] ?? '');
        self::assertSame('100', $req['amount'] ?? '');
        self::assertSame('张三', $req['re_user_name'] ?? '');

        // 全链路核心：单笔转账请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站转账请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站转账请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testRedPacketSendSignsOutboundSendredpackViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->redPacketSend([
            'mch_billno'  => 'RP_20260816_001',
            'send_name'   => '商户A',
            're_openid'   => 'OPENID_XYZ',
            'total_amount' => 100,
            'wishing'     => '恭喜发财',
            'act_name'    => '开业活动',
            'remark'      => 'remark',
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 mmpaymkttransfers/sendredpack（普通现金红包）
        self::assertStringContainsString('mmpaymkttransfers/sendredpack', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 mmpaymkttransfers/sendredpack 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('RP_20260816_001', $req['mch_billno'] ?? '');
        self::assertSame('OPENID_XYZ', $req['re_openid'] ?? '');
        self::assertSame('100', $req['total_amount'] ?? '');

        // 全链路核心：发放红包请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站红包请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站红包请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testRedPacketGroupSignsOutboundSendgroupredpackViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->redPacketGroup([
            'mch_billno'   => 'RP_G_20260816_001',
            'send_name'    => '商户A',
            're_openid'    => 'OPENID_XYZ',
            'total_amount' => 300,
            'total_num'    => 3,
            'wishing'      => '恭喜发财',
            'act_name'     => '开业活动',
            'remark'       => 'remark',
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 mmpaymkttransfers/sendgroupredpack（裂变红包 / 群红包）
        self::assertStringContainsString('mmpaymkttransfers/sendgroupredpack', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 mmpaymkttransfers/sendgroupredpack 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('RP_G_20260816_001', $req['mch_billno'] ?? '');
        self::assertSame('3', $req['total_num'] ?? '');
        // 裂变红包固定为随机金额分配
        self::assertSame('ALL_RAND', $req['amt_type'] ?? '');

        // 全链路核心：发放裂变红包请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站裂变红包请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站裂变红包请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testRedPacketQuerySignsOutboundGethbinfoViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->redPacketQuery('RP_20260816_001');

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 mmpaymkttransfers/gethbinfo（查询红包发放记录）
        self::assertStringContainsString('mmpaymkttransfers/gethbinfo', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 mmpaymkttransfers/gethbinfo 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('RP_20260816_001', $req['mch_billno'] ?? '');
        // 商户侧查询固定 bill_type=MCHT
        self::assertSame('MCHT', $req['bill_type'] ?? '');

        // 全链路核心：查询红包请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站查询红包请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站查询红包请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testRedPacketGroupTotalNumBelowThreeNormalizedToApiException(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        // 网关对裂变红包 total_num < 3 抛 PayException，经 PaysBridge::invokeGateway 归一为 ApiException
        $this->expectException(ApiException::class);

        $adapter->redPacketGroup([
            'mch_billno'   => 'RP_G_20260816_BAD',
            'send_name'    => '商户A',
            're_openid'    => 'OPENID_XYZ',
            'total_amount' => 300,
            'total_num'    => 2,
            'wishing'      => '恭喜发财',
            'act_name'     => '开业活动',
            'remark'       => 'remark',
        ]);
    }
}
