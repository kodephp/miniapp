<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Core\GatewayFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 能力矩阵「声明⟺实现零漂移」一致性回归。
 *
 * miniapp 桥接层以 GatewayFactory::getGatewayClass(channel) + 类级 method_exists 做能力发现，
 * paymentCapabilities() / supports*() 必须严格镜像 vendor/kode/pays 2.17.0 各网关类的真实实现，
 * 不得出现「声明支持却未实现」或「已实现却报不支持」的漂移。
 *
 * 本测试以「真实网关类」为唯一事实源（SoT），独立重算每个渠道的能力矩阵，并断言与
 * PaysBridgePayAdapter::paymentCapabilities() 完全一致——任何网关实现变更导致的漂移都会被捕获。
 */
final class PaysBridgeCapabilityMatrixConsistencyTest extends TestCase
{
    /**
     * 能力键 → kode/pays 网关原生方法名（与 PaysBridgePayAdapter 各 supports* 映射严格一致）
     *
     * @var array<string, string>
     */
    private const FEATURE_MAP = [
        'profit_sharing'   => 'createProfitSharing',
        'transfer'         => 'singleTransfer',
        'reconciliation'   => 'downloadBill',
        'red_packet'       => 'sendRedPacket',
        'subscription'     => 'createSubscription',
        'balance'          => 'queryBalance',
        'settlement'       => 'settleToWallet',
        'personal_receive' => 'createQrCode',
        'webhook'          => 'verifyWebhook',
        'refund'           => 'refund',
    ];

    /**
     * paymentCapabilities() 必须与真实网关类实现逐键一致（零漂移）。
     */
    #[DataProvider('channelProvider')]
    public function testPaymentCapabilitiesMirrorRealGatewayClasses(Channel $channel, string $gatewayMethod): void
    {
        $adapter  = PaysBridge::adapter($channel, fn () => []);
        $expected = $this->expectedCapabilities($gatewayMethod);

        self::assertSame(
            $expected,
            $adapter->paymentCapabilities(),
            sprintf('渠道 [%s] 的能力矩阵必须与真实网关类 %s 的实现严格一致', $channel->label(), $gatewayMethod),
        );
    }

    /**
     * 关键不变量：微信 V2 网关无 queryBalance 但其余核心能力齐备；支付宝全量；
     * 抖音仅分账 + webhook；QQ 仅 webhook。任何一个漂移都会破坏「零漂移」契约。
     *
     * @param array<string, bool> $expected
     */
    #[DataProvider('invariantsProvider')]
    public function testSupportsInvariants(Channel $channel, array $expected): void
    {
        $adapter = PaysBridge::adapter($channel, fn () => []);

        foreach ($expected as $method => $want) {
            self::assertSame(
                $want,
                $adapter->$method(),
                sprintf('渠道 [%s] 的 %s() 必须与真实网关类实现一致', $channel->label(), $method),
            );
        }
    }

    /**
     * @return array<string, array{0: Channel, 1: string}>
     */
    public static function channelProvider(): array
    {
        return [
            '微信'   => [Channel::WechatMini, 'wechat'],
            '支付宝' => [Channel::AlipayMini, 'alipay'],
            '抖音'   => [Channel::DouyinMini, 'douyin'],
            'QQ'     => [Channel::Qq, 'qq'],
        ];
    }

    /**
     * @return array<string, array{0: Channel, 1: array<string, bool>}>
     */
    public static function invariantsProvider(): array
    {
        return [
            '微信' => [Channel::WechatMini, [
                'supportsProfitSharing'   => true,
                'supportsTransfer'        => true,
                'supportsReconciliation'  => true,
                'supportsRedPacket'       => true,
                'supportsSubscription'    => true,
                'supportsBalance'         => false,
                'supportsSettlement'      => true,
                'supportsPersonalReceive' => true,
                'supportsWebhook'         => true,
                'supportsRefund'          => true,
            ]],
            '支付宝' => [Channel::AlipayMini, [
                'supportsProfitSharing'   => true,
                'supportsTransfer'        => true,
                'supportsReconciliation'  => true,
                'supportsRedPacket'       => true,
                'supportsSubscription'    => true,
                'supportsBalance'         => true,
                'supportsSettlement'      => true,
                'supportsPersonalReceive' => true,
                'supportsWebhook'         => true,
                'supportsRefund'          => true,
            ]],
            '抖音' => [Channel::DouyinMini, [
                'supportsProfitSharing'   => true,
                'supportsTransfer'        => false,
                'supportsReconciliation'  => false,
                'supportsRedPacket'       => false,
                'supportsSubscription'    => false,
                'supportsBalance'         => false,
                'supportsSettlement'      => false,
                'supportsPersonalReceive' => false,
                'supportsWebhook'         => true,
                'supportsRefund'          => true,
            ]],
            'QQ' => [Channel::Qq, [
                'supportsProfitSharing'   => false,
                'supportsTransfer'        => false,
                'supportsReconciliation'  => false,
                'supportsRedPacket'       => false,
                'supportsSubscription'    => false,
                'supportsBalance'         => false,
                'supportsSettlement'      => false,
                'supportsPersonalReceive' => false,
                'supportsWebhook'         => true,
                'supportsRefund'          => true,
            ]],
        ];
    }

    /**
     * 以真实网关类为 SoT 独立重算能力矩阵。
     *
     * @return array<string, bool>
     */
    private function expectedCapabilities(string $gatewayMethod): array
    {
        /** @var class-string|null $cls */
        $cls = GatewayFactory::getGatewayClass($gatewayMethod);

        $map = [];
        foreach (self::FEATURE_MAP as $cap => $feature) {
            $map[$cap] = $cls !== null && method_exists($cls, $feature);
        }

        return $map;
    }
}
