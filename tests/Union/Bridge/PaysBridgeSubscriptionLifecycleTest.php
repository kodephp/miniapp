<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 微信 V2「委托代扣」订阅生命周期其余操作端到端（签名拼装全链路）验证。
 *
 * 与 {@see PaysBridgeSubscriptionSignChainTest}（仅覆盖 createSubscription → papay/entrustweb 签约链接）
 * 形成闭环：本测试覆盖签约之后真正「出站」的订阅操作——取消订阅（papay/deletecontract）与查询订阅
 * （papay/querycontract），二者均经真实 WechatPayGateway + FakePaysHttpClient 走通「参数校验 / 签名 /
 * 报文拼装 / 响应解析」真实代码路径而不触网。
 *
 * 同时证明：微信委托代扣无「暂停」端点（pauseSubscription 在网关层抛 methodNotSupported），桥接经
 * invokeGateway 归一为 ApiException，而非静默下行或 Call to undefined method。
 *
 * 断言核心：真实网关对出站请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 经
 * Signer::verifyMd5 重新校验通过——即「桥接只做路由，签名由真实 kode/pays 网关完成」的全链路证据。
 */
final class PaysBridgeSubscriptionLifecycleTest extends TestCase
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

    public function testCancelSubscriptionSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        // 以委托代扣协议号（contract_id）解约
        $result = $adapter->subscriptionCancel('CONTRACT_123');

        // 真实网关解析成功响应（FakePaysHttpClient 返回的微信 V2 成功报文）
        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 papay/deletecontract（申请解约）
        self::assertStringContainsString('papay/deletecontract', $this->fake->lastUrl ?? '');

        // 出站请求体为合法 XML 且携带真实签约标识与解约备注
        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 papay/deletecontract 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('CONTRACT_123', $req['contract_id'] ?? '');
        self::assertSame('用户申请解约', $req['contract_termination_remark'] ?? '');

        // 全链路核心：取消订阅请求必须做了 MD5 签名，且可用同一 api_key 重算校验通过
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站解约请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站解约请求签名必须可用 api_key 重新校验通过（验证网关签名拼装完整）',
        );
    }

    public function testGetSubscriptionSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        // 以委托代扣协议号查询签约关系
        $result = $adapter->subscriptionGet('CONTRACT_123');

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 papay/querycontract（查询签约关系）
        self::assertStringContainsString('papay/querycontract', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 papay/querycontract 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('CONTRACT_123', $req['contract_id'] ?? '');

        // 全链路核心：查询订阅请求同样做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站查询订阅请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站查询订阅请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testPauseSubscriptionUnsupportedNormalizedToApiException(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 微信委托代扣无「暂停」端点：网关抛 methodNotSupported → 桥接 invokeGateway 归一为 ApiException
        $this->expectException(ApiException::class);

        $adapter->subscriptionPause('CONTRACT_123');
    }
}
