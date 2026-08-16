<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 真实高级支付能力端到端（签名拼装全链路）验证。
 *
 * 与下单 {@see PaysBridgeCreateOrderSignChainTest}、退款 {@see PaysBridgeRefundSignChainTest} 对称：
 * 转账（singleTransfer）/ 分账（createProfitSharing）/ 红包（sendRedPacket）在 kode/pays 2.3.0 同样走
 * 微信 V2「XML + MD5 签名」路径（网关方法经 signedV2Post(..., true) 带证书发起）。
 * 本测试经真实 WechatPayGateway + FakePaysHttpClient 走通「参数校验 / 签名 / 报文拼装 / 响应解析」
 * 真实代码路径而不触网，并额外断言：
 *  - 真实网关对出站高级能力请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 重新校验通过；
 *  - 经真实 Kernel resolver 的 Union::advancedPay() 门面同样产出已签名请求；
 *  - 缺必填参数时网关在参数校验阶段即经 invokeGateway 归一为 ApiException 抛错（非静默下行）。
 *
 * 这是「高级支付能力签名拼装全链路」的闭环证据：桥接只负责身份/能力路由，签名与报文由真实
 * kode/pays 网关完成，且 method_exists 守卫确保不支持的渠道/方法清晰报错而非 Call to undefined method。
 */
final class PaysBridgeAdvancedSignChainTest extends TestCase
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

    public function testTransferSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->transferSingle([
            'out_biz_no' => 'T20260816001',
            'amount'     => 100,
            'recipient'  => ['type' => 'OPENID', 'account' => 'oABC123', 'name' => '张三'],
        ]);

        // 真实网关解析成功响应（FakePaysHttpClient 返回的微信 V2 成功报文）
        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('mmpaymkttransfers/promotion/transfers', $this->fake->lastUrl ?? '');

        // 出站请求体为合法 XML
        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向微信企业付款接口发送 XML 报文');

        $req = $this->parseWechatXml($body);

        // 业务参数必须进入出站请求并参与签名
        self::assertSame('T20260816001', $req['partner_trade_no'] ?? '');
        self::assertSame('oABC123', $req['openid'] ?? '');
        self::assertSame('100', (string) ($req['amount'] ?? ''));

        // 全链路核心：真实网关必须对出站转账请求做了 MD5 签名，且签名可用同一 api_key 重算校验通过
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站转账请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站转账请求签名必须可用 api_key 重新校验通过（验证网关签名拼装完整）',
        );
    }

    public function testProfitSharingSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->profitSharingCreate([
            'transaction_id' => 'TXN_20260816',
            'out_order_no'   => 'PS_20260816_001',
            'receivers'      => [
                ['type' => 'MERCHANT_ID', 'account' => 'mch_2', 'amount' => 100],
            ],
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('secapi/pay/profitsharing', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向微信分账接口发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('TXN_20260816', $req['transaction_id'] ?? '');
        self::assertSame('PS_20260816_001', $req['out_order_no'] ?? '');
        // receivers 以 JSON 字符串形式进入报文
        self::assertStringContainsString('mch_2', $req['receivers'] ?? '');

        self::assertArrayHasKey('sign', $req, '真实网关必须对出站分账请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站分账请求签名必须可用 api_key 重新校验通过（验证网关签名拼装完整）',
        );
    }

    public function testRedPacketSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->redPacketSend([
            'mch_billno'  => 'HB_20260816_001',
            'send_name'   => '测试商家',
            're_openid'   => 'oABC123',
            'total_amount' => 100,
            'wishing'     => '恭喜发财',
            'act_name'    => '开业活动',
            'remark'      => '备注',
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('mmpaymkttransfers/sendredpack', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向微信红包接口发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('HB_20260816_001', $req['mch_billno'] ?? '');
        self::assertSame('oABC123', $req['re_openid'] ?? '');
        self::assertSame('100', (string) ($req['total_amount'] ?? ''));

        self::assertArrayHasKey('sign', $req, '真实网关必须对出站红包请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站红包请求签名必须可用 api_key 重新校验通过（验证网关签名拼装完整）',
        );
    }

    public function testAdvancedViaRealKernelResolverThroughUnionFacade(): void
    {
        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx_app',
                    'secret' => 'wechat-secret',
                    'mch_id' => 'mch_1',
                    'key'    => self::API_KEY,
                ],
            ],
            new FakeHttpClient(),
        );
        $kernel->union();

        $advanced = $kernel->union()->wechat()->advancedPay();
        self::assertInstanceOf(PaysBridgePayAdapter::class, $advanced);

        // 转账经门面同样产出已签名请求
        $t = $advanced->transferSingle([
            'out_biz_no' => 'T_FACADE_1',
            'amount'     => 200,
            'recipient'  => ['type' => 'OPENID', 'account' => 'oFacade', 'name' => '李四'],
        ]);
        self::assertSame('SUCCESS', $t['return_code'] ?? null);
        self::assertStringContainsString('mmpaymkttransfers/promotion/transfers', $this->fake->lastUrl ?? '');
        $tReq = $this->parseWechatXml((string) ($this->fake->lastRawBody ?? ''));
        self::assertTrue(Signer::verifyMd5($tReq, self::API_KEY), '门面级转账必须产出已签名请求');

        // 分账经门面同样产出已签名请求
        $p = $advanced->profitSharingCreate([
            'transaction_id' => 'TXN_FACADE',
            'out_order_no'   => 'PS_FACADE_1',
            'receivers'      => [['type' => 'MERCHANT_ID', 'account' => 'mch_x', 'amount' => 50]],
        ]);
        self::assertSame('SUCCESS', $p['return_code'] ?? null);
        self::assertStringContainsString('secapi/pay/profitsharing', $this->fake->lastUrl ?? '');
        $pReq = $this->parseWechatXml((string) ($this->fake->lastRawBody ?? ''));
        self::assertTrue(Signer::verifyMd5($pReq, self::API_KEY), '门面级分账必须产出已签名请求');

        // 参数校验门面（缺 out_biz_no / amount / recipient → 网关参数校验阶段即归一抛错）
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('out_biz_no');

        $advanced->transferSingle(['amount' => 100]);
    }

    public function testGatewayRejectsTransferWithoutRequiredParams(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 网关 singleTransfer 在参数校验阶段即要求 out_biz_no / amount / recipient，
        // 经 invokeGateway 归一为 ApiException（非静默下行）
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('out_biz_no');

        $adapter->transferSingle(['amount' => 100]);
    }
}
