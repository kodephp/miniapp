<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgeNotifyAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 真实微信 V2 回调验签端到端验证（money-confirmation 路径）。
 *
 * 与下单 / 退款 / 高级能力「签名拼装全链路」对称，本测试覆盖「收钱」闭环的最后一环——
 * 支付结果回调（异步通知）验签。微信 V2 回调验签是纯本地 MD5 验签
 * （{@see \Kode\Pays\Gateway\Wechat\WechatPayGateway::verifyNotify()} 仅做
 * Signer::verifyMd5($data, api_key)），不经过网络。本测试经真实 WechatPayGateway + 真实
 * Signer 走通真实代码路径，证明：
 *  - 用与网关一致的 api_key 预签的回调报文，经桥接可验签通过并返回可信业务数组；
 *  - 篡改报文（不改签名）被正确拒绝——无静默放行伪造/篡改的 money 事件；
 *  - 缺 sign 字段被正确拒绝；
 *  - 经真实 Kernel resolver 的 Union::wechat()->notify()->decode() 门面同样闭环。
 *
 * 这是「回调验签」的闭环证据：桥接把验签交给真实 kode/pays 网关，且不静默放行任何失败。
 */
final class PaysBridgeNotifyVerifyTest extends TestCase
{
    /**
     * 微信 V2 回调验签用的 api_key（须与传给网关的 config.api_key 完全一致）
     */
    private const API_KEY = 'unit_test_api_key_0123456789';

    protected function setUp(): void
    {
        // 隔离 kode/pays 门面静态状态，避免跨测试污染（验签本身不触网，仅防御性设置）
        Pay::setHttpClient(new FakePaysHttpClient(self::API_KEY));
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    /**
     * 构造一笔真实微信 V2 回调报文（业务字段）+ 用与网关一致的 api_key 做 MD5 签名。
     *
     * @return array<string, string>
     */
    private function signedNotifyPayload(): array
    {
        $payload = [
            'appid'         => 'wx_app',
            'mch_id'        => 'mch_1',
            'out_trade_no'  => 'T_NOTIFY_20260816',
            'transaction_id' => 'TXN_4200001234567890',
            'openid'        => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o',
            'result_code'   => 'SUCCESS',
            'return_code'   => 'SUCCESS',
            'total_fee'     => '100',
            'cash_fee'      => '100',
            'time_end'      => '20260816120000',
            'nonce_str'     => '5K8264ILTKCH16CQ2502SI8Z',
        ];

        // 与微信 V2 网关 verifyNotify 完全一致的签名算法（MD5，排除空值，末尾拼 &key=）
        $payload['sign'] = Signer::md5($payload, self::API_KEY);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function wechatConfig(): array
    {
        return ['app_id' => 'wx_app', 'mch_id' => 'mch_1', 'api_key' => self::API_KEY];
    }

    public function testValidWechatV2NotifyDecodesToBusinessArray(): void
    {
        $payload = $this->signedNotifyPayload();

        // 防御性独立校验：报文签名确实可由同一 api_key 验过（证明不是桩）
        self::assertTrue(
            Signer::verifyMd5($payload, self::API_KEY),
            '回调报文签名必须可用 api_key 重新校验通过',
        );

        $notify = PaysBridge::notifyAdapter(Channel::WechatMini, fn () => $this->wechatConfig());
        self::assertInstanceOf(PaysBridgeNotifyAdapter::class, $notify);

        $result = $notify->decode($payload);

        // 验签通过返回可信业务数组（V2 无密文，原样返回解析后的报文）
        self::assertSame('T_NOTIFY_20260816', $result['out_trade_no'] ?? '');
        self::assertSame('TXN_4200001234567890', $result['transaction_id'] ?? '');
        self::assertSame('SUCCESS', $result['result_code'] ?? '');
        // 关键：验签通过的报文必须保留 sign，且业务字段未被丢弃
        self::assertArrayHasKey('sign', $result);
        self::assertSame('100', (string) ($result['total_fee'] ?? ''));
    }

    public function testTamperedNotifyRejectedNoSilentSuccess(): void
    {
        $payload = $this->signedNotifyPayload();

        // 篡改业务字段但不重新签名——模拟伪造/篡改的 money 事件
        $tampered = $payload;
        $tampered['out_trade_no'] = 'T_NOTIFY_HACKED';
        $tampered['total_fee'] = '999999';

        $notify = PaysBridge::notifyAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 篡改后签名校验必失败 → 桥接必须抛 RuntimeException（无静默放行）
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('验签失败');

        $notify->decode($tampered);
    }

    public function testMissingSignRejected(): void
    {
        $payload = $this->signedNotifyPayload();
        unset($payload['sign']);

        $notify = PaysBridge::notifyAdapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 缺 sign 字段 → 网关 verifyNotify 直接返回 false → 桥接抛 RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('验签失败');

        $notify->decode($payload);
    }

    public function testFacadeNotifyThroughRealKernelResolver(): void
    {
        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx_app',
                    'secret' => 'wechat-secret',
                    'mch_id' => 'mch_1',
                    'key'    => self::API_KEY,
                ],
            ],
            new FakeHttpClient(),
        );
        $kernel->union();

        $notify = $kernel->union()->wechat()->notify();
        self::assertInstanceOf(PaysBridgeNotifyAdapter::class, $notify);

        $payload = $this->signedNotifyPayload();
        $result = $notify->decode($payload);

        self::assertSame('T_NOTIFY_20260816', $result['out_trade_no'] ?? '');
        self::assertSame('TXN_4200001234567890', $result['transaction_id'] ?? '');
        self::assertArrayHasKey('sign', $result);
    }
}
