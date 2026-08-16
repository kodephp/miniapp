<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 余额查询能力的跨渠道支持矩阵 e2e。
 *
 * 与 v2.0.24（微信余额「明确不支持」守卫）和 v2.0.25（支付宝余额真实 RSA2 签名链）形成完整闭环：
 * 本测试把「余额查询」在 miniapp 全部已桥接消费渠道上的支持态一次性坐实——
 *   - 支付宝：supportsBalance()=true，且 gateway 类真实含 queryBalance（v2.0.25 已证签名链）
 *   - 微信 / 抖音 / QQ：supportsBalance()=false，且调用 balanceQuery() 经 method_exists 守卫
 *     真实抛 RuntimeException（非静默下行），证明「不支持」是各渠道一致的硬契约。
 *
 * 关键机制（非桩）：
 *   - supportsBalance() 走 GatewayFactory::getGatewayClass(channel) + 类级 method_exists，
 *     直接反映 vendor/kode/pays 2.17.0 各网关类的真实实现（无需构造实例、无需完整支付配置）。
 *   - balanceQuery() 先 paysGateway() 构造真实网关实例，再经 callGatewayFeature 的 method_exists
 *     守卫抛 RuntimeException；构造所需最小配置取自各渠道既有 notify 测试的验证。
 */
final class PaysBridgeBalanceSupportMatrixTest extends TestCase
{
    private string $privateKeyPem;

    protected function setUp(): void
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        self::assertNotFalse($res, 'openssl 必须可用以生成测试 RSA 密钥');

        $priv = '';
        openssl_pkey_export($res, $priv);
        $this->privateKeyPem = $priv;

        // 不支持渠道在构造实例后即抛守卫异常，不会触网；设 fake 仅作防御。
        Pay::setHttpClient(new FakePaysHttpClient());
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    public function testAlipaySupportsBalance(): void
    {
        $adapter = $this->adapter(Channel::AlipayMini);

        self::assertTrue(
            $adapter->supportsBalance(),
            '支付宝 gateway 类真实含 queryBalance，supportsBalance() 必须为 true',
        );
    }

    public function testWechatDoesNotSupportBalance(): void
    {
        $adapter = $this->adapter(Channel::WechatMini);

        self::assertFalse($adapter->supportsBalance(), '微信 V2 网关无 queryBalance');
        $this->expectBalanceUnsupported($adapter, Channel::WechatMini);
    }

    public function testDouyinDoesNotSupportBalance(): void
    {
        $adapter = $this->adapter(Channel::DouyinMini);

        self::assertFalse($adapter->supportsBalance(), '抖音网关无 queryBalance');
        $this->expectBalanceUnsupported($adapter, Channel::DouyinMini);
    }

    public function testQqDoesNotSupportBalance(): void
    {
        $adapter = $this->adapter(Channel::Qq);

        self::assertFalse($adapter->supportsBalance(), 'QQ 网关无 queryBalance');
        $this->expectBalanceUnsupported($adapter, Channel::Qq);
    }

    /**
     * 不支持余额查询的渠道，调用 balanceQuery() 必须真实抛 RuntimeException（非静默下行）。
     */
    private function expectBalanceUnsupported(PaysBridgePayAdapter $adapter, Channel $channel): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($channel->label());
        $this->expectExceptionMessage('余额');

        $adapter->balanceQuery();
    }

    /**
     * 按渠道返回最小可用配置的 PaysBridgePayAdapter（配置取自各渠道既有 notify 测试）。
     */
    private function adapter(Channel $channel): PaysBridgePayAdapter
    {
        return match ($channel) {
            Channel::AlipayMini => PaysBridge::adapter($channel, fn () => [
                'app_id'      => 'alipay_app_20260816',
                'private_key' => $this->privateKeyPem,
                'public_key'  => 'alipay_public_dummy',
            ]),
            Channel::WechatMini => PaysBridge::adapter($channel, fn () => [
                'app_id'      => 'wx_app',
                'mch_id'      => 'mch_1',
                'api_key'     => 'unit_test_api_key_0123456789',
                'serial_no'   => 'TEST_SERIAL_NO_001',
                'private_key' => $this->privateKeyPem,
            ]),
            Channel::DouyinMini => PaysBridge::adapter($channel, fn () => [
                'app_id'      => 'douyin_app_20260816',
                'merchant_id' => 'douyin_merchant_001',
                'salt'        => 'douyin_salt_secret_7f3a',
            ]),
            Channel::Qq => PaysBridge::adapter($channel, fn () => [
                'app_id'      => 'qq_app_20260816',
                'mch_id'      => 'qq_mch_001',
                'api_key'     => 'qq_api_key_9c2e7b',
                'serial_no'   => 'qq_serial_001',
                'private_key' => 'qq_dummy_private_key_not_used_for_balance',
            ]),
            default => throw new \InvalidArgumentException("测试未覆盖渠道 {$channel->label()}"),
        };
    }
}
