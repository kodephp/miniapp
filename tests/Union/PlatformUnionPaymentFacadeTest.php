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

    /**
     * 携带「完整商户配置」的 Kernel（mch_id / key / api_v3_key），
     * 用于验证默认 Kernel resolver 能把这些字段正确翻译为 kode/pays 网关 config，
     * 并构造出真实 WechatPayGateway 执行能力方法（端到端，不触网）。
     */
    private function kernelWithMerchant(): Kernel
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
                'mch_id'     => '1900000000',
                'key'        => 'unit_test_api_key_0123456789abcdef',
                'api_v3_key' => 'unit_test_api_v3_key_0123456789ab',
            ],
            'alipay' => [
                'app_id'      => '2024...',
                'private_key' => 'priv-key',
                'public_key'  => 'pub-key',
            ],
        ]);
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

    /**
     * 经「真实 Kernel resolver + 完整商户配置」验证 paymentCapabilities() 返回的微信能力矩阵精确正确。
     *
     * WechatPayGateway（V2）实现 8 个 Capable 接口：分账 / 转账 / 对账 / 红包 / 订阅 / 结算 / 个人收款 / 退款；
     * 未实现 BalanceCapable / WebhookCapable，故 balance / webhook 为 false。
     */
    public function testWechatPaymentCapabilitiesExactViaKernelResolver(): void
    {
        $caps = $this->kernelWithMerchant()->union()->wechat()->paymentCapabilities();

        self::assertSame([
            'profit_sharing'   => true,
            'transfer'         => true,
            'reconciliation'   => true,
            'red_packet'       => true,
            'subscription'     => true,
            'balance'          => false,
            'settlement'       => true,
            'personal_receive' => true,
            'webhook'          => false,
            'refund'           => true,
        ], $caps);
    }

    /**
     * 门面级便捷入口 paymentCapabilities() 必须与 advancedPay()->paymentCapabilities() 完全一致
     * （同一底层能力发现，仅少持有一个适配器实例）。
     */
    public function testTopLevelPaymentCapabilitiesEqualsAdvancedPay(): void
    {
        $union = $this->kernelWithMerchant()->union();

        self::assertSame(
            $union->wechat()->advancedPay()->paymentCapabilities(),
            $union->wechat()->paymentCapabilities(),
        );
    }

    /**
     * 支付宝能力矩阵结构正确（10 个能力键、值均为布尔），不耦合具体能力取值。
     */
    public function testAlipayPaymentCapabilitiesStructure(): void
    {
        $caps = $this->kernelWithMerchant()->union()->alipay()->paymentCapabilities();

        self::assertSame([
            'profit_sharing',
            'transfer',
            'reconciliation',
            'red_packet',
            'subscription',
            'balance',
            'settlement',
            'personal_receive',
            'webhook',
            'refund',
        ], array_keys($caps));

        foreach ($caps as $value) {
            self::assertIsBool($value);
        }
    }

    /**
     * 端到端：经真实 Kernel resolver（携带完整商户配置）构造真实 WechatPayGateway，
     * 调用一个「不触网」的对账解析能力 reconciliationParseBill，验证 resolver 注入的商户凭证
     * 能正确构造网关、且网关特色方法被真实委托执行（解析结果正确）。
     */
    public function testWechatAdvancedPayParsesBillViaRealKernelResolver(): void
    {
        $adapter = $this->kernelWithMerchant()->union()->wechat()->advancedPay();

        // 微信对账单 CSV：首行表头被跳过，数据行 >= MIN_FIELDS(10) 即解析为具名记录。
        $csv = "交易时间,app_id,商户号,sub_mch_id,device_info,transaction_id,out_trade_no,"
            . "openid,trade_type,trade_state,bank_type,currency,total_fee\n"
            . "`2024-01-01 00:00:00`,`wxapp0000000000`,`1900000000`,`sub`,`dev`,"
            . "`txn123`,`OUT20240101`,`openid123`,`JSAPI`,`SUCCESS`,`CASH`,`CNY`,`100`\n";

        $records = $adapter->reconciliationParseBill($csv);

        self::assertNotEmpty($records);
        self::assertSame('OUT20240101', $records[0]['out_trade_no']);
        self::assertSame('100', $records[0]['total_fee']);
    }
}
