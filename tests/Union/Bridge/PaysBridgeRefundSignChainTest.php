<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Bridge\PaysBridgeRefundAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 真实退款端到端（签名拼装全链路）验证。
 *
 * 与测试下单的 {@see PaysBridgeCreateOrderSignChainTest} 对称：退款在 kode/pays 2.3.0 同样走
 * 微信 V2「XML + MD5 签名」路径（网关方法 refund / applyRefund 均经
 * signedV2Post('secapi/pay/refund', …, true)）。本测试经真实 WechatPayGateway + FakePaysHttpClient
 * 走通「参数校验 / 签名 / 报文拼装 / 响应解析」真实代码路径而不触网，并额外断言：
 *  - 真实网关对出站退款请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 重新校验通过；
 *  - 经真实 Kernel resolver 的 Union::refund()->applyRefund() 门面同样产出已签名退款请求；
 *  - 缺必填参数时网关在参数校验阶段即经 invokeGateway 归一为 ApiException 抛错（非静默下行）。
 *
 * 这是「退款签名拼装全链路」的闭环证据：桥接只负责身份/能力路由，签名与报文由真实 kode/pays 网关完成。
 */
final class PaysBridgeRefundSignChainTest extends TestCase
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
     * 注意：不能直接 json_encode(simplexml) 再 decode——空元素（如退款的 refund_desc）
     * 会被解析成空数组 [] 而非空字符串，导致 Signer::verifyMd5 遍历时触发
     * "Array to string conversion"。逐节点显式 (string) 取文本可避免该问题。
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

    public function testRefundSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->refund([
            'out_refund_no' => 'R20260816001',
            'total_fee'     => 100,
            'refund_fee'    => 100,
            'out_trade_no'  => 'T_ORIG_1',
        ]);

        // 真实网关解析成功响应（FakePaysHttpClient 返回的微信 V2 成功报文）
        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('secapi/pay/refund', $this->fake->lastUrl ?? '');
        self::assertStringNotContainsString('pay/unifiedorder', $this->fake->lastUrl ?? '');

        // 出站请求体为合法 XML
        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向微信退款接口发送 XML 报文');

        $req = $this->parseWechatXml($body);

        // 退款业务参数必须进入出站请求并参与签名
        self::assertSame('R20260816001', $req['out_refund_no'] ?? '');
        self::assertSame('100', (string) ($req['refund_fee'] ?? ''));

        // 全链路核心：真实网关必须对出站退款请求做了 MD5 签名，且签名可用同一 api_key 重算校验通过
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站退款请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站退款请求签名必须可用 api_key 重新校验通过（验证网关签名拼装完整）',
        );
    }

    public function testRefundViaRealKernelResolverThroughUnionFacade(): void
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

        $refund = $kernel->union()->wechat()->refund();
        self::assertInstanceOf(PaysBridgeRefundAdapter::class, $refund);

        $result = $refund->applyRefund([
            'out_refund_no' => 'R_FACADE_1',
            'refund_fee'    => 100,
            'out_trade_no'  => 'T_ORIG_F',
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertStringContainsString('secapi/pay/refund', $this->fake->lastUrl ?? '');

        $req = $this->parseWechatXml((string) ($this->fake->lastRawBody ?? ''));
        self::assertSame('R_FACADE_1', $req['out_refund_no'] ?? '');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '门面级退款同样必须产出已签名请求',
        );

        // 参数校验门面（缺 out_refund_no / refund_fee → 网关参数校验阶段即归一抛错）
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('out_refund_no');

        $refund->applyRefund(['out_trade_no' => 'T_ORIG_X']);
    }

    public function testGatewayRejectsRefundWithoutRequiredParams(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 网关 refund() 在参数校验阶段即要求 out_refund_no / total_fee / refund_fee，
        // 经 invokeGateway 归一为 ApiException（非静默下行）
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('out_refund_no');

        $adapter->refund(['refund_fee' => 100]);
    }
}
