<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\NoCapabilityGateway;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\WebhookCapableTestGateway;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgeWebhookAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\WebhookAdapter;
use Kode\Pays\Core\GatewayFactory;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Gateway\Wechat\WechatPayGateway;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 验证 PaysBridge Webhook 事件能力委托到 kode/pays 网关的 WebhookCapableInterface。
 *
 * 2.0 起 kode/pays 为硬依赖（vendor 已安装真实 kode/pays）。本测试：
 *  - 用 {@see WebhookCapableTestGateway}（实现 WebhookCapableInterface）替换 wechat 网关注册，
 *    验证 verify / parse 正确转发到网关方法；
 *  - 用 {@see NoCapabilityGateway}（未实现该接口）替换，验证 method_exists 守卫抛清晰异常；
 *  - 验证能力发现 supportsWebhook() 矩阵（微信 true / 百度 false）。
 */
final class PaysBridgeWebhookTest extends TestCase
{
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
     * 构造一笔真实 MD5 签名的微信 V2 Webhook 报文（与 verifyNotify 同源验签）。
     *
     * @return array<string, string>
     */
    private function signedWebhookPayload(): array
    {
        $payload = [
            'appid'         => 'wx_app',
            'mch_id'        => 'mch_1',
            'out_trade_no'  => 'T_WH_1',
            'transaction_id' => 'TXN_WH_1',
            'result_code'   => 'SUCCESS',
            'return_code'   => 'SUCCESS',
            'total_fee'     => '100',
            'nonce_str'     => 'WHNONCE123',
        ];
        $payload['sign'] = Signer::md5($payload, self::API_KEY);

        return $payload;
    }

    /**
     * 将关联数组转为微信 V2 风格 XML 报文（无 CDATA，网关 xmlToArray 以 LIBXML_NOCDATA 解析一致）。
     *
     * @param array<string, string> $data
     */
    private function toXml(array $data): string
    {
        $xml = new \SimpleXMLElement('<xml/>');
        foreach ($data as $key => $value) {
            $xml->addChild($key, (string) $value);
        }

        /** @var string $out */
        $out = $xml->asXML();

        return $out;
    }

    /**
     * 用指定网关注册临时替换 wechat（测试隔离），finally 中还原真实 WechatPayGateway。
     *
     * @param class-string<\Kode\Pays\Contract\GatewayInterface> $gatewayClass
     */
    private function withWechatGateway(string $gatewayClass, callable $test): void
    {
        Pay::clearCache('wechat');
        GatewayFactory::unregister('wechat');
        Pay::register('wechat', $gatewayClass);

        try {
            $test();
        } finally {
            Pay::clearCache('wechat');
            GatewayFactory::unregister('wechat');
            Pay::register('wechat', WechatPayGateway::class);
            Pay::clearCache('wechat');
        }
    }

    public function testAdapterImplementsWebhookAdapter(): void
    {
        $adapter = PaysBridge::webhookAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(WebhookAdapter::class, $adapter);
        self::assertInstanceOf(PaysBridgeWebhookAdapter::class, $adapter);
    }

    public function testVerifyDelegatesToGateway(): void
    {
        $this->withWechatGateway(WebhookCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::webhookAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            self::assertTrue($adapter->verify('SIGNED_EVENT_PAYLOAD', ['X-Signature' => 'x']));
        });
    }

    public function testVerifyRejectsInvalidPayload(): void
    {
        $this->withWechatGateway(WebhookCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::webhookAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            self::assertFalse($adapter->verify('TAMPERED_PAYLOAD', ['X-Signature' => 'x']));
        });
    }

    public function testParseDelegatesToGateway(): void
    {
        $this->withWechatGateway(WebhookCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::webhookAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $event = $adapter->parse('SIGNED_EVENT_PAYLOAD');

            self::assertSame('wechat', $event['gateway']);
            self::assertSame('EV_1', $event['event_id']);
            self::assertSame('refund.success', $event['event_type']);
            self::assertSame('SIGNED_EVENT_PAYLOAD', $event['raw']);
        });
    }

    public function testSupportsWebhookTrueForGatewayWithMethod(): void
    {
        // 用提供 verifyWebhook 方法的测试网关替换 wechat（模拟支持 Webhook 的网关）
        $this->withWechatGateway(WebhookCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

            self::assertTrue($adapter->supportsWebhook());
        });
    }

    public function testSupportsWebhookTrueForPublishedWechat(): void
    {
        // kode/pays 2.17.0 起，已发布的微信 V2 网关实现了 WebhookCapableInterface，
        // 能力发现必须自动返回 true（前向兼容脚手架在此版本正式激活）
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertTrue($adapter->supportsWebhook());
    }

    public function testPublishedWechatGatewayWebhookVerifyParseE2E(): void
    {
        // 真实已发布 kode/pays 2.17.0 的微信 V2 网关已实现 WebhookCapableInterface，
        // 本测试经真实网关走通「验签 + 解析」全链路（与 notify 同源 MD5 验签，不触网），
        // 证明前向兼容脚手架在 2.17.0 已正式生效、且 Webhook 路径无静默放行。
        $adapter = PaysBridge::webhookAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 能力发现已激活
        $pay = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());
        self::assertTrue($pay->supportsWebhook());

        // 构造一笔真实 MD5 签名的微信 V2 Webhook 报文（XML，与 verifyNotify 同源验签）
        $payload = $this->signedWebhookPayload();
        $xml = $this->toXml($payload);

        // 验签通过（V2 验签在报文体内，headers 不参与 MD5，传空亦可）
        self::assertTrue($adapter->verify($xml, []));

        // 解析为统一事件结构
        $event = $adapter->parse($xml);
        self::assertSame('wechat', $event['gateway']);
        self::assertSame('TXN_WH_1', $event['event_id']);
        self::assertSame('pay_success', $event['event_type']);
        self::assertSame('T_WH_1', $event['data']['out_trade_no'] ?? '');
        self::assertSame($xml, $event['raw']);

        // 篡改报文（不改签名）验签必失败——无静默放行伪造/篡改事件
        $tampered = $payload;
        $tampered['out_trade_no'] = 'T_WH_HACKED';
        self::assertFalse($adapter->verify($this->toXml($tampered), []));

        // 空报文验签失败
        self::assertFalse($adapter->verify('', []));
    }

    public function testSupportsWebhookFalseForBaidu(): void
    {
        // 百度网关未在 kode/pays 注册，能力发现须返回 false（而非抛异常）
        $adapter = PaysBridge::adapter(Channel::BaiduMini, fn () => $this->wechatConfig());

        self::assertFalse($adapter->supportsWebhook());
    }

    public function testVerifyThrowsWhenGatewayNotWebhookCapable(): void
    {
        $this->withWechatGateway(NoCapabilityGateway::class, function (): void {
            $adapter = PaysBridge::webhookAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Webhook');

            $adapter->verify('SIGNED_EVENT_PAYLOAD', []);
        });
    }

    public function testParseThrowsWhenGatewayNotWebhookCapable(): void
    {
        $this->withWechatGateway(NoCapabilityGateway::class, function (): void {
            $adapter = PaysBridge::webhookAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Webhook');

            $adapter->parse('SIGNED_EVENT_PAYLOAD');
        });
    }
}
