<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use InvalidArgumentException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\CryptoCapableTestGateway;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\AdvancedPayAdapter;
use Kode\MiniApp\Union\Contracts\CryptoAdapter;
use Kode\MiniApp\Union\Contracts\RefundAdapter;
use Kode\MiniApp\Union\Contracts\WebhookAdapter;
use Kode\Pays\Core\GatewayFactory;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Gateway\Coinbase\CoinbaseGateway;
use PHPUnit\Framework\TestCase;

/**
 * 支付能力门面冒烟测试：验证经「真实 Kernel config resolver」组装出的
 * refund / webhook / advancedPay / crypto 适配器返回正确类型与清晰异常，
 * 覆盖此前缺失的「端到端门面接线」验证（区别于 PaysBridge*Test 的纯桥接单测）。
 */
final class PlatformUnionPaymentFacadeTest extends TestCase
{
    private FakePaysHttpClient $fake;

    protected function setUp(): void
    {
        $this->fake = new FakePaysHttpClient('unit_test_api_key_0123456789');
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    private function kernel(): Kernel
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
            'alipay' => [
                'app_id' => '2024...',
            ],
        ]);
        // 触发 union 初始化
        $kernel->union();
        return $kernel;
    }

    public function testWechatRefundReturnsRefundAdapter(): void
    {
        $adapter = $this->kernel()->union()->wechat()->refund();
        self::assertInstanceOf(RefundAdapter::class, $adapter);
    }

    public function testAlipayRefundReturnsRefundAdapter(): void
    {
        $adapter = $this->kernel()->union()->alipay()->refund();
        self::assertInstanceOf(RefundAdapter::class, $adapter);
    }

    public function testWechatWebhookReturnsWebhookAdapter(): void
    {
        $adapter = $this->kernel()->union()->wechat()->webhook();
        self::assertInstanceOf(WebhookAdapter::class, $adapter);
    }

    public function testWechatAdvancedPayReturnsAdvancedPayAdapter(): void
    {
        $adapter = $this->kernel()->union()->wechat()->advancedPay();
        self::assertInstanceOf(AdvancedPayAdapter::class, $adapter);
    }

    public function testWechatCryptoReturnsCryptoAdapter(): void
    {
        $adapter = $this->kernel()->union()->wechat()->crypto();
        self::assertInstanceOf(CryptoAdapter::class, $adapter);
    }

    /**
     * 加密货币不在 miniapp Kernel 默认凭证体系内，未注入自定义 resolver 时，
     * 默认 Kernel resolver 必须在能力方法被调用时抛「crypto platform 缺失」的清晰引导。
     */
    public function testCryptoDefaultResolverThrowsClearException(): void
    {
        $crypto = $this->kernel()->union()->wechat()->crypto();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('crypto');

        $crypto->createCryptoOrder(['crypto_currency' => 'BTC', 'fiat_amount' => 100]);
    }

    /**
     * 端到端正向冒烟：通过 Union::crypto(channel, resolver) 注入自定义 resolver，
     * 临时把 coinbase 网关注册为测试夹具，验证门面到网关的真实委托链路。
     */
    public function testCryptoWithCustomResolverDelegatesToGateway(): void
    {
        Pay::clearCache('coinbase');
        GatewayFactory::unregister('coinbase');
        Pay::register('coinbase', CryptoCapableTestGateway::class);

        try {
            $kernel = $this->kernel();
            $crypto = $kernel->union()->crypto(
                Channel::Crypto,
                static fn (): array => ['api_key' => 'unit_test_api_key_0123456789'],
            );

            self::assertInstanceOf(CryptoAdapter::class, $crypto);

            $order = $crypto->createCryptoOrder(['crypto_currency' => 'BTC', 'fiat_amount' => 100]);
            self::assertSame('createCryptoOrder', $order['_method']);
            self::assertSame('BTC', $order['crypto_currency']);

            $addrs = $crypto->getPaymentAddresses('BTC');
            self::assertSame('getPaymentAddresses', $addrs['_method']);

            self::assertTrue($crypto->verifyNotify(['signature' => 'valid']));
        } finally {
            Pay::clearCache('coinbase');
            GatewayFactory::unregister('coinbase');
            Pay::register('coinbase', CoinbaseGateway::class);
            Pay::clearCache('coinbase');
        }
    }
}
