<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

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
 * 真实查询类端点端到端（签名拼装全链路）验证。
 *
 * 与测试下单（{@see PaysBridgeCreateOrderSignChainTest}）/ 退款（{@see PaysBridgeRefundSignChainTest}）
 * 对称：微信 V2 的查单 / 关单 / 查退款均走「XML + MD5 签名」路径（网关方法 queryOrder /
 * closeOrder / queryRefund 均经 signedV2Post），此前从未做端到端验证。本测试经真实
 * WechatPayGateway + FakePaysHttpClient 走通「参数校验 / 签名 / 报文拼装 / 响应解析」真实代码路径
 * 而不触网，并断言：
 *  - 真实网关对出站请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 重新校验通过；
 *  - 业务参数（out_trade_no / out_refund_no / transaction_id）确实进入出站报文；
 *  - 经真实 Kernel resolver 的 Union::wechat()->queryOrder() 门面同样产出已签名请求；
 *  - queryOrder 的 wx 前缀订单号分支（走 transaction_id 而非 out_trade_no）同样闭环。
 *
 * 这是「查询签名拼装全链路」的闭环证据：桥接只负责身份/能力路由，签名与报文由真实 kode/pays 完成。
 */
final class PaysBridgeQuerySignChainTest extends TestCase
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
     * 注意：不能直接 json_encode(simplexml) 再 decode——空元素会被解析成空数组 [] 而非空字符串，
     * 导致 Signer::verifyMd5 遍历时触发 "Array to string conversion"。逐节点显式 (string) 取文本可避免。
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

    public function testQueryOrderSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());
        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->queryOrder('T_QUERY_001');

        // 真实网关解析成功响应（FakePaysHttpClient 返回的微信 V2 成功报文）
        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('pay/orderquery', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向微信查单接口发送 XML 报文');

        $req = $this->parseWechatXml($body);
        self::assertSame('T_QUERY_001', $req['out_trade_no'] ?? '');
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站查单请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站查单请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testQueryOrderUsesTransactionIdWhenWxPrefixed(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->queryOrder('wx4200001234567890');
        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        $req = $this->parseWechatXml((string) ($this->fake->lastRawBody ?? ''));
        // wx 前缀订单号 → 网关走 transaction_id 分支，不应再出现 out_trade_no
        self::assertSame('wx4200001234567890', $req['transaction_id'] ?? '');
        self::assertArrayNotHasKey('out_trade_no', $req);
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            'wx 前缀分支的出站查单请求同样必须已签名',
        );
    }

    public function testCloseOrderSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->closeOrder('T_CLOSE_001');
        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('pay/closeorder', $this->fake->lastUrl ?? '');

        $req = $this->parseWechatXml((string) ($this->fake->lastRawBody ?? ''));
        self::assertSame('T_CLOSE_001', $req['out_trade_no'] ?? '');
        self::assertArrayHasKey('sign', $req);
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出城关单请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testQueryRefundSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->queryRefund('R_QUERY_001');
        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('pay/refundquery', $this->fake->lastUrl ?? '');

        $req = $this->parseWechatXml((string) ($this->fake->lastRawBody ?? ''));
        self::assertSame('R_QUERY_001', $req['out_refund_no'] ?? '');
        self::assertArrayHasKey('sign', $req);
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站查退款请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testQueryOrderViaRealKernelResolverThroughUnionFacade(): void
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

        // 查询类能力经门面 pay() 适配器（PaysBridgePayAdapter）暴露
        $result = $kernel->union()->wechat()->pay()->queryOrder('T_FACADE_Q1');
        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('pay/orderquery', $this->fake->lastUrl ?? '');

        $req = $this->parseWechatXml((string) ($this->fake->lastRawBody ?? ''));
        self::assertSame('T_FACADE_Q1', $req['out_trade_no'] ?? '');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '门面级查单同样必须产出已签名请求',
        );
    }
}
