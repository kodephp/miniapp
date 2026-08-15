<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\PayExceptionCapableTestGateway;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Core\GatewayFactory;
use Kode\Pays\Core\PayException;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Gateway\Wechat\WechatPayGateway;
use PHPUnit\Framework\TestCase;

/**
 * 验证桥接层把 kode/pays 网关抛出的 PayException 统一归一化为本包 ApiException。
 *
 * 2.0 之前网关在「参数校验 / 签名 / 报文拼装 / 响应解析」阶段抛出的 Kode\Pays\Core\PayException
 * 会以原始类型直接冒泡，与本包「平台业务错误统一为 ApiException（无静默成功）」的约定不一致。
 * 本测试断言：
 *  - 直连网关调用（createOrder）参数校验失败的 PayException 被归一化为 ApiException，
 *    且保留原始 errorCode / gateway 标签 / capability / gateway 原始码与信息，并链入 previous；
 *  - 高级能力委托（callGatewayFeature → 转账 singleTransfer）同样经归一化出口捕获 PayException。
 */
final class PaysBridgeExceptionNormalizationTest extends TestCase
{
    /**
     * 微信 V2 响应用于验签的 api_key（须与传给网关的 config.api_key 完全一致）
     */
    private const API_KEY = 'unit_test_api_key_0123456789';

    protected function setUp(): void
    {
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
     * 直连网关：JSAPI 缺 openid 触发真实网关参数校验 PayException，被归一化为 ApiException。
     *
     * 该异常在网关「参数校验」阶段即抛出（早于任何 HTTP 请求），因此无需网络。
     */
    public function testCreateOrderParamErrorNormalizedToApiException(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        try {
            // 不传 UnionUser → 桥接不注入 openid → 网关参数校验抛 PayException
            $adapter->createOrder([
                'out_trade_no' => 'T_ERR',
                'total_fee'    => 1,
                'body'         => 'x',
                'trade_type'   => 'JSAPI',
            ]);
            self::fail('期望 createOrder 抛 ApiException');
        } catch (ApiException $e) {
            // 原始 errorCode 被保留（业务参数错误 1004）
            self::assertSame(PayException::ERROR_PARAM, $e->errorCode());
            // 原始 PayException 作为 previous 链入
            self::assertInstanceOf(PayException::class, $e->getPrevious());
            // 归一化载荷携带渠道标签与能力名
            self::assertSame('微信小程序', $e->payload()['gateway']);
            self::assertSame('下单', $e->payload()['capability']);
            // 消息包含原始网关错误文案
            self::assertStringContainsString('openid', $e->getMessage());
        }
    }

    /**
     * 高级能力委托路径（callGatewayFeature）同样经归一化出口捕获 PayException。
     *
     * 临时把 wechat 网关替换为「singleTransfer 抛真实 PayException」的夹具，验证后还原，
     * 避免污染其它测试的网关注册表。
     */
    public function testAdvancedCapabilityPayExceptionNormalized(): void
    {
        Pay::clearCache('wechat');
        GatewayFactory::unregister('wechat');
        Pay::register('wechat', PayExceptionCapableTestGateway::class);

        try {
            $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

            try {
                $adapter->transferSingle(['out_biz_no' => 'B1', 'amount' => 100]);
                self::fail('期望 transferSingle 抛 ApiException');
            } catch (ApiException $e) {
                // 网关业务错误码 1005 被保留
                self::assertSame(PayException::ERROR_GATEWAY, $e->errorCode());
                // 网关原始错误码与信息透传进载荷
                self::assertSame('INSUFFICIENT_BALANCE', $e->payload()['gateway_code']);
                self::assertSame('余额不足', $e->payload()['gateway_message']);
                self::assertSame('微信小程序', $e->payload()['gateway']);
                self::assertSame('转账', $e->payload()['capability']);
                self::assertInstanceOf(PayException::class, $e->getPrevious());
                self::assertStringContainsString('余额不足', $e->getMessage());
            }
        } finally {
            GatewayFactory::unregister('wechat');
            Pay::register('wechat', WechatPayGateway::class);
            Pay::clearCache('wechat');
        }
    }

    /**
     * 桥接层自身的契约错误（如付款人渠道不匹配）仍按原样抛出 RuntimeException，
     * 不经由 PayException 归一化出口，两者互不影响。
     */
    public function testBridgeLevelContractErrorIsNotNormalized(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不属于同一平台');

        // 支付宝付款人付到微信渠道 → 桥接层契约校验失败，抛 RuntimeException/InvalidArgumentException
        $adapter->createOrder(
            ['out_trade_no' => 'T_X', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI'],
            new \Kode\MiniApp\Union\UnionUser(unionId: '', openId: 'ALI_UID', channel: Channel::AlipayMini),
        );
    }
}
