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

    public function testSupportsWebhookFalseForPublishedWechat(): void
    {
        // 已发布 kode/pays 2.3.0 的微信网关尚未提供 verifyWebhook 方法，能力发现须返回 false
        // （pay_open 2.6.0 起才支持 WebhookCapableInterface，届时本方法自动返回 true）
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertFalse($adapter->supportsWebhook());
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
