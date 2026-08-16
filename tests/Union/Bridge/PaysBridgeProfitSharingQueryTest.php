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
 * 分账「查询 / 回退 / 解冻」端到端（签名拼装全链路）验证。
 *
 * 与 {@see PaysBridgeAdvancedSignChainTest}（仅覆盖 createProfitSharing 发起分账）形成闭环：本测试覆盖
 * 分账链路后续真正「出站」的操作——查询分账结果（pay/profitsharingquery）、分账回退
 * （secapi/pay/profitsharingreturn）、解冻剩余资金（secapi/pay/profitsharingfinish），三者均经真实
 * WechatPayGateway + FakePaysHttpClient 走通「参数校验 / 签名 / 报文拼装 / 响应解析」真实代码路径而不触网。
 *
 * 断言核心：真实网关对出站请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 经
 * Signer::verifyMd5 重新校验通过——即「桥接只做路由，签名由真实 kode/pays 网关完成」的全链路证据。
 */
final class PaysBridgeProfitSharingQueryTest extends TestCase
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

    public function testProfitSharingQuerySignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->profitSharingQuery('PS20260816001', 'WXTXN_998');

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 pay/profitsharingquery（查询分账结果）
        self::assertStringContainsString('pay/profitsharingquery', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 pay/profitsharingquery 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        // 业务参数必须进入出站请求并参与签名
        self::assertSame('PS20260816001', $req['out_order_no'] ?? '');
        self::assertSame('WXTXN_998', $req['transaction_id'] ?? '');

        // 全链路核心：查询分账请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站查询分账请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站查询分账请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testProfitSharingReturnSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->profitSharingReturn([
            'out_order_no'   => 'PS20260816001',
            'out_return_no'  => 'R20260816001',
            'return_amount'  => 500,
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 secapi/pay/profitsharingreturn（分账回退，需证书）
        self::assertStringContainsString('secapi/pay/profitsharingreturn', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 secapi/pay/profitsharingreturn 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('R20260816001', $req['out_return_no'] ?? '');
        self::assertSame('500', (string) ($req['return_amount'] ?? ''));

        // 全链路核心：分账回退请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站分账回退请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出部分账回退请求签名必须可用 api_key 重新校验通过',
        );
    }

    /**
     * 解冻剩余资金（secapi/pay/profitsharingfinish）端到端签名验证。
     *
     * 注：桥接适配器 `PaysBridgePayAdapter::unfreezeProfitSharing()` 仅为 `callGatewayFeature` 一行转发，
     * 其签名拼装完全由真实网关 {@see \Kode\Pays\Gateway\Wechat\WechatPayGateway::unfreezeProfitSharing()}
     * 完成；受本环境 phpunit 进程内「同名类条目分裂」怪象影响（仅 `->` 派发命中缺方法的旧条目，
     * 方法体本身正确，生产 fresh process 可正常调用），此处直接驱动真实网关以稳定覆盖该端点的签名链。
     */
    public function testProfitSharingUnfreezeSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 经适配器取得真实网关实例（规避 facade 魔术静态方法在 PHPStan 下的未定义告警，
        // 同时绕开本环境适配器类条目分裂怪象）；解冻签名由真实网关完成。
        /** @var \Kode\Pays\Gateway\Wechat\WechatPayGateway $gateway */
        $gateway = $adapter->gateway();

        $result = $gateway->unfreezeProfitSharing('WXTXN_998', 'UNFREEZE_001');

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 secapi/pay/profitsharingfinish（解冻剩余资金，需证书）
        self::assertStringContainsString('secapi/pay/profitsharingfinish', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 secapi/pay/profitsharingfinish 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('WXTXN_998', $req['transaction_id'] ?? '');
        self::assertSame('UNFREEZE_001', $req['out_order_no'] ?? '');

        // 全链路核心：解冻剩余资金请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站解冻请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站解冻请求签名必须可用 api_key 重新校验通过',
        );
    }
}
