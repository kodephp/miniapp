<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Union\Bridge\Fixtures\CryptoCapableTestGateway;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\NoCapabilityGateway;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgeCryptoAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\CryptoAdapter;
use Kode\Pays\Core\GatewayFactory;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Gateway\Wechat\WechatPayGateway;
use PHPUnit\Framework\TestCase;

/**
 * 验证 PaysBridge 加密货币能力委托到 kode/pays 网关的 CryptoCapableInterface。
 *
 * 2.0 起 kode/pays 为硬依赖（vendor 已安装真实 kode/pays）。本测试：
 *  - 用 {@see CryptoCapableTestGateway}（提供全部加密货币方法）替换 coinbase 网关注册，
 *    验证各方法正确转发到网关（含 verifyNotify 返回 bool）；
 *  - 用 {@see NoCapabilityGateway}（未实现该接口）替换，验证 method_exists 守卫抛清晰异常；
 *  - 验证加密货币渠道（Channel::Crypto）经 gatewayMethod 映射到 coinbase 门面方法。
 */
final class PaysBridgeCryptoTest extends TestCase
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
    private function coinbaseConfig(): array
    {
        return ['api_key' => self::API_KEY];
    }

    /**
     * 用指定网关注册临时替换 coinbase（测试隔离），finally 中还原真实 CoinbaseGateway 之前
     * 先用 WechatPayGateway 占位（避免还原到一个本测试未引入的类）。
     *
     * @param class-string<\Kode\Pays\Contract\GatewayInterface> $gatewayClass
     */
    private function withCoinbaseGateway(string $gatewayClass, callable $test): void
    {
        Pay::clearCache('coinbase');
        GatewayFactory::unregister('coinbase');
        Pay::register('coinbase', $gatewayClass);

        try {
            $test();
        } finally {
            Pay::clearCache('coinbase');
            GatewayFactory::unregister('coinbase');
            Pay::register('coinbase', WechatPayGateway::class);
            Pay::clearCache('coinbase');
        }
    }

    public function testAdapterImplementsCryptoAdapter(): void
    {
        $adapter = PaysBridge::cryptoAdapter(Channel::Crypto, fn () => $this->coinbaseConfig());

        self::assertInstanceOf(CryptoAdapter::class, $adapter);
        self::assertInstanceOf(PaysBridgeCryptoAdapter::class, $adapter);
    }

    public function testCreateCryptoOrderDelegatesToGateway(): void
    {
        $this->withCoinbaseGateway(CryptoCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::cryptoAdapter(Channel::Crypto, fn () => $this->coinbaseConfig());

            $result = $adapter->createCryptoOrder(['crypto_currency' => 'BTC', 'fiat_amount' => 100]);

            self::assertSame('BTC', $result['crypto_currency']);
            self::assertSame('createCryptoOrder', $result['_method']);
        });
    }

    public function testCreateOrderAndQueryDelegatesToGateway(): void
    {
        $this->withCoinbaseGateway(CryptoCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::cryptoAdapter(Channel::Crypto, fn () => $this->coinbaseConfig());

            self::assertSame('createOrder', $adapter->createOrder(['fiat_amount' => 50])['_method']);
            self::assertSame('Q1', $adapter->getPaymentAddresses('Q1')['order_id']);
            self::assertSame('Q1', $adapter->getConfirmations('Q1')['order_id']);
            self::assertSame('Q1', $adapter->queryOrder('Q1')['order_id']);
            self::assertSame('refund', $adapter->refund(['order_id' => 'Q1'])['_method']);
        });
    }

    public function testGetExchangeRateDefaultsToUsd(): void
    {
        $this->withCoinbaseGateway(CryptoCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::cryptoAdapter(Channel::Crypto, fn () => $this->coinbaseConfig());

            $rate = $adapter->getExchangeRate('ETH');
            self::assertSame('ETH', $rate['crypto']);
            self::assertSame('USD', $rate['fiat']);
            self::assertSame('getExchangeRate', $rate['_method']);

            $rate2 = $adapter->getExchangeRate('ETH', 'CNY');
            self::assertSame('CNY', $rate2['fiat']);
        });
    }

    public function testVerifyNotifyReturnsBool(): void
    {
        $this->withCoinbaseGateway(CryptoCapableTestGateway::class, function (): void {
            $adapter = PaysBridge::cryptoAdapter(Channel::Crypto, fn () => $this->coinbaseConfig());

            self::assertTrue($adapter->verifyNotify(['signature' => 'valid']));
            self::assertFalse($adapter->verifyNotify(['signature' => 'bad']));
        });
    }

    public function testUnsupportedCryptoMethodThrowsClearException(): void
    {
        $this->withCoinbaseGateway(NoCapabilityGateway::class, function (): void {
            $adapter = PaysBridge::cryptoAdapter(Channel::Crypto, fn () => $this->coinbaseConfig());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('加密货币');

            $adapter->createCryptoOrder(['crypto_currency' => 'BTC']);
        });
    }

    public function testCryptoChannelMapsToCoinbaseGateway(): void
    {
        // Channel::Crypto 经 gatewayMethod 必须映射到 kode/pays 的 coinbase 门面方法，
        // 否则 PaysBridgeCryptoAdapter 无法解析到加密货币网关（见上方各委托用例的真实注册验证）。
        $adapter = PaysBridge::cryptoAdapter(Channel::Crypto, fn () => $this->coinbaseConfig());

        self::assertSame(Channel::Crypto, $adapter->channel());
    }
}
