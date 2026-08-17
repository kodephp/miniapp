<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * balanceQueryDayEnd（→ kode/pays queryDayEndBalance）能力行为契约。
 *
 * 经实测（vendor/kode/pays 2.17.0）：
 *  - 支付宝网关虽「声明」queryDayEndBalance（method_exists=true），但其实现是
 *    `throw PayException::methodNotSupported(...)` 的占位桩——即「声明存在、实现抛不支持」。
 *  - 微信 / 抖音 / QQ 网关根本未实现该方法（method_exists=false）。
 *
 * 本测试锁定桥接层的真实行为：无论在哪一渠道调用 balanceQueryDayEnd 都「响亮失败、绝不静默成功」——
 * 支付宝经 invokeGateway 归一为 ApiException（透传 methodNotSupported 信息），
 * 其余渠道经 callGatewayFeature 的 method_exists 守卫抛 RuntimeException（明确「不支持 [余额] 能力」）。
 * 此契约证明 miniapp 不存在「声明⟺实现漂移」导致的静默成功路径。
 */
final class PaysBridgeAlipayBalanceDayEndCapabilityTest extends TestCase
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

    public function testAlipayDayEndBalanceFailsLoudViaApiException(): void
    {
        $key = openssl_pkey_get_private($this->privateKeyPem);
        self::assertNotFalse($key, 'openssl 必须可解析测试私钥');
        $detail = openssl_pkey_get_details($key);
        self::assertNotFalse($detail, 'openssl_pkey_get_details 必须返回密钥明细');
        $publicKeyPem = (string) $detail['key'];

        $adapter = PaysBridge::adapter(Channel::AlipayMini, fn () => [
            'app_id'      => 'alipay_app',
            'private_key' => $this->privateKeyPem,
            'public_key'  => $publicKeyPem,
            'notify_url'  => 'https://example.com/notify',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('queryDayEndBalance');

        $adapter->balanceQueryDayEnd('2026-08-16');
    }

    public function testWechatDayEndBalanceRejectedByCapabilityGuard(): void
    {
        $this->expectCapabilityRejection(Channel::WechatMini);
    }

    public function testDouyinDayEndBalanceRejectedByCapabilityGuard(): void
    {
        $this->expectCapabilityRejection(Channel::DouyinMini);
    }

    public function testQqDayEndBalanceRejectedByCapabilityGuard(): void
    {
        $this->expectCapabilityRejection(Channel::Qq);
    }

    private function expectCapabilityRejection(Channel $channel): void
    {
        $adapter = PaysBridge::adapter($channel, fn () => $this->channelConfig($channel));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($channel->label());
        $this->expectExceptionMessage('余额');

        $adapter->balanceQueryDayEnd('2026-08-16');
    }

    /**
     * @return array<string, mixed>
     */
    private function channelConfig(Channel $channel): array
    {
        return match ($channel) {
            Channel::WechatMini => [
                'app_id'      => 'wx_app',
                'mch_id'      => 'mch_1',
                'api_key'     => 'unit_test_api_key_0123456789',
                'serial_no'   => 'TEST_SERIAL_NO_001',
                'private_key' => $this->privateKeyPem,
            ],
            Channel::DouyinMini => [
                'app_id'      => 'douyin_app_20260816',
                'merchant_id' => 'douyin_merchant_001',
                'salt'        => 'douyin_salt_secret_7f3a',
            ],
            Channel::Qq => [
                'app_id'      => 'qq_app_20260816',
                'mch_id'      => 'qq_mch_001',
                'api_key'     => 'qq_api_key_9c2e7b',
                'serial_no'   => 'qq_serial_001',
                'private_key' => 'qq_dummy_private_key_not_used',
            ],
            default => throw new \InvalidArgumentException("测试未覆盖渠道 {$channel->label()}"),
        };
    }
}
