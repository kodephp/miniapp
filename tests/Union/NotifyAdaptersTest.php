<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;

/**
 * 全平台 Union 回调适配器（归一化层）回归测试。
 *
 * 微信 / 支付宝 / 百度 / 抖音 / 企业微信 5 个回调适配器此前零测试覆盖，
 * 而回调（支付确认、消息推送）是生产关键路径。这些适配器为纯归一化层
 * （不发起 HTTP、不调用 Provider 方法），本测试锁定其 channel() 与
 * decode() 的字段映射行为，杜绝后续重构漂移。
 *
 * （QQ 回调适配器因含 XML+MD5 验签，单独在 QqNotifyAdapterTest 覆盖。）
 */
final class NotifyAdaptersTest extends TestCase
{
    private function buildUnion(): Union
    {
        $kernel = new Kernel(
            [
                'wechat'      => ['app_id' => 'wx_miniapp', 'secret' => 's', 'cache' => new ArrayCache()],
                'alipay'      => ['app_id' => 'ali_app', 'secret' => 's', 'cache' => new ArrayCache()],
                'baidu'       => ['app_id' => 'bd_app', 'secret' => 's', 'cache' => new ArrayCache()],
                'douyin'      => ['app_id' => 'dy_app', 'secret' => 's', 'cache' => new ArrayCache()],
                'wechat_work' => ['app_id' => 'ww_app', 'secret' => 's', 'cache' => new ArrayCache()],
            ],
            new FakeHttpClient(),
        );

        return $kernel->union();
    }

    public function testWechatNotifyResolvesAndNormalizes(): void
    {
        $notify = $this->buildUnion()->wechat()->notify();

        self::assertInstanceOf(NotifyAdapter::class, $notify);
        self::assertSame(Channel::WechatMini, $notify->channel());

        $result = $notify->decode([
            'out_trade_no'  => 'W1',
            'transaction_id' => 'WT1',
            'total_fee'     => '200',
            'openid'        => 'WO1',
            'result_code'   => 'SUCCESS',
        ]);

        self::assertSame('W1', $result['out_trade_no']);
        self::assertSame('WT1', $result['transaction_id']);
        self::assertSame(200, $result['total_fee']);
        self::assertSame('WO1', $result['openid']);
        self::assertSame('SUCCESS', $result['result_code']);
        self::assertArrayHasKey('raw', $result);
    }

    public function testAlipayNotifyResolvesAndNormalizes(): void
    {
        $notify = $this->buildUnion()->alipay()->notify();

        self::assertInstanceOf(NotifyAdapter::class, $notify);
        self::assertSame(Channel::AlipayMini, $notify->channel());

        $result = $notify->decode([
            'out_trade_no' => 'A1',
            'trade_no'     => 'AT1',
            'total_amount' => '9.99',
            'trade_status' => 'TRADE_SUCCESS',
        ]);

        self::assertSame('A1', $result['out_trade_no']);
        self::assertSame('AT1', $result['trade_no']);
        self::assertSame('9.99', $result['total_amount']);
        self::assertSame('TRADE_SUCCESS', $result['trade_status']);
        self::assertArrayHasKey('raw', $result);
    }

    public function testBaiduNotifyResolvesAndNormalizes(): void
    {
        $notify = $this->buildUnion()->baidu()->notify();

        self::assertInstanceOf(NotifyAdapter::class, $notify);
        self::assertSame(Channel::BaiduMini, $notify->channel());

        $result = $notify->decode([
            'out_trade_no' => 'B1',
            'trade_no'     => 'BT1',
            'status'       => '1',
        ]);

        self::assertSame('B1', $result['out_trade_no']);
        self::assertSame('BT1', $result['trade_no']);
        self::assertSame('1', $result['status']);
        self::assertArrayHasKey('raw', $result);
    }

    public function testDouyinNotifyResolvesAndNormalizes(): void
    {
        $notify = $this->buildUnion()->douyin()->notify();

        self::assertInstanceOf(NotifyAdapter::class, $notify);
        self::assertSame(Channel::DouyinMini, $notify->channel());

        $result = $notify->decode([
            'out_trade_no' => 'D1',
            'trade_no'     => 'DT1',
            'result_code'  => 'SUCCESS',
        ]);

        self::assertSame('D1', $result['out_trade_no']);
        self::assertSame('DT1', $result['trade_no']);
        self::assertSame('SUCCESS', $result['result_code']);
        self::assertArrayHasKey('raw', $result);
    }

    public function testWechatWorkNotifyResolvesAndNormalizes(): void
    {
        $notify = $this->buildUnion()->wechatWork()->notify();

        self::assertInstanceOf(NotifyAdapter::class, $notify);
        self::assertSame(Channel::WechatWork, $notify->channel());

        $result = $notify->decode([
            'Event' => 'subscribe',
            'FromUserName' => 'userid_1',
        ]);

        self::assertSame('subscribe', $result['event_type']);
        self::assertArrayHasKey('raw', $result);
    }
}
