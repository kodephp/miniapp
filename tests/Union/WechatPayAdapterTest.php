<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;

/**
 * 微信支付适配器（Union 层接线）回归测试
 *
 * 验证 `Union::pay(Channel::WechatMini)->unifiedOrder([...])` 正确分派到
 * `Wechat\Modules\Pay::order()`（V3 JSAPI 统一下单 /pay/transactions/jsapi），
 * 返回预下单响应（prepay_id）。
 *
 * HTTP 层以 {@see FakeHttpClient} 桩替换，避免真实请求。
 */
final class WechatPayAdapterTest extends TestCase
{
    private function buildUnion(): Union
    {
        $http = (new FakeHttpClient())
            ->stub('pay/transactions/jsapi', [
                'prepay_id' => 'wx_prepay_wechat_1',
                'code'      => 'SUCCESS',
            ]);

        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id'  => 'wx_app',
                    'secret'  => 'wechat-secret',
                    'mch_id'  => 'wechat_mch',
                    'cache'   => new ArrayCache(),
                ],
            ],
            $http,
        );

        return $kernel->union();
    }

    public function testWechatPayAdapterIsResolved(): void
    {
        $pay = $this->buildUnion()->pay(Channel::WechatMini);

        self::assertInstanceOf(PayAdapter::class, $pay);
        self::assertSame(Channel::WechatMini, $pay->channel());
    }

    public function testWechatPayUnifiedOrderDispatchesToProviderOrder(): void
    {
        $result = $this->buildUnion()->pay(Channel::WechatMini)->unifiedOrder([
            'out_trade_no' => 'WX_ORDER_1',
            'description'  => '商品',
            'amount'       => ['total' => 100],
            'payer'        => ['openid' => 'OPENID_1'],
        ]);

        // 底层 Wechat\Modules\Pay::order() 被调用（命中 V3 JSAPI 下单地址）
        self::assertSame('wx_prepay_wechat_1', $result['prepay_id'] ?? null);
    }

    public function testWechatMpPayResolvesSameAdapter(): void
    {
        $pay = $this->buildUnion()->pay(Channel::WechatMp);

        self::assertInstanceOf(PayAdapter::class, $pay);
        self::assertSame(Channel::WechatMini, $pay->channel());
    }
}
