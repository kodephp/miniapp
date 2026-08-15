<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\NoCapabilityGateway;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\RefundCapableTestGateway;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgeRefundAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\RefundAdapter;
use Kode\Pays\Core\GatewayFactory;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Gateway\Wechat\WechatPayGateway;
use PHPUnit\Framework\TestCase;

/**
 * 验证 PaysBridge 退款能力委托到 kode/pays 网关的 RefundCapableInterface。
 *
 * 2.0 起 kode/pays 为硬依赖（vendor 已安装真实 kode/pays）。本测试：
 *  - 用 {@see RefundCapableTestGateway}（提供 applyRefund / queryRefund / cancelRefund）替换 wechat
 *    网关注册，验证三个方法正确转发到网关；
 *  - 用 {@see NoCapabilityGateway}（未实现该接口）替换，验证 method_exists 守卫抛清晰异常；
 *  - 验证能力发现 supportsRefund() 矩阵（微信 true / 百度 false）。
 */
final class PaysBridgeRefundTest extends TestCase
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

    public function testAdapterImplementsRefundAdapter(): void
    {
        $adapter = PaysBridge::refundAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(RefundAdapter::class, $adapter);
        self::assertInstanceOf(PaysBridgeRefundAdapter::class, $adapter);
    }

    public function testApplyRefundDelegatesToGateway(): void
    {
        $this->withWechatGateway(RefundCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::refundAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $result = $adapter->applyRefund(['out_refund_no' => 'R1', 'amount' => 100]);

            self::assertSame('R1', $result['out_refund_no']);
            self::assertSame('applyRefund', $result['_method']);
        });
    }

    public function testQueryRefundDelegatesToGateway(): void
    {
        $this->withWechatGateway(RefundCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::refundAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $result = $adapter->queryRefund('R1');

            self::assertSame('R1', $result['out_refund_no']);
            self::assertSame('queryRefund', $result['_method']);
        });
    }

    public function testCancelRefundDelegatesToGateway(): void
    {
        $this->withWechatGateway(RefundCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::refundAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $result = $adapter->cancelRefund('R1');

            self::assertSame('R1', $result['out_refund_no']);
            self::assertSame('cancelRefund', $result['_method']);
        });
    }

    public function testSupportsRefundTrueForPublishedWechat(): void
    {
        // kode/pays 2.3.0 已发布的微信网关已实现 RefundCapableInterface（applyRefund），
        // 能力发现须返回 true（与 Webhook 不同，Webhook 在 2.3.0 尚不存在）。
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertTrue($adapter->supportsRefund());
    }

    public function testSupportsRefundFalseForBaidu(): void
    {
        // 百度网关未在 kode/pays 注册，能力发现须返回 false（而非抛异常）
        $adapter = PaysBridge::adapter(Channel::BaiduMini, fn () => $this->wechatConfig());

        self::assertFalse($adapter->supportsRefund());
    }

    public function testApplyRefundThrowsWhenGatewayNotRefundCapable(): void
    {
        $this->withWechatGateway(NoCapabilityGateway::class, function (): void {
            $adapter = PaysBridge::refundAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('退款');

            $adapter->applyRefund(['out_refund_no' => 'R1']);
        });
    }

    /**
     * queryRefund 与核心 PayAdapter::queryRefund 同义（方法名一致），任何合法网关注册都实现它，
     * 因此 method_exists 守卫不会对其触发「未支持」异常——这里仅验证 applyRefund / cancelRefund
     * 缺位时会抛异常即可（见上、下两个用例）。
     */

    public function testCancelRefundThrowsWhenGatewayNotRefundCapable(): void
    {
        $this->withWechatGateway(NoCapabilityGateway::class, function (): void {
            $adapter = PaysBridge::refundAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('退款');

            $adapter->cancelRefund('R1');
        });
    }
}
