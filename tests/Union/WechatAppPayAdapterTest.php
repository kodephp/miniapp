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
 * 微信 App 支付适配器（Union 层接线）回归测试
 *
 * 验证 `Union::pay(Channel::WechatApp)->unifiedOrder([...])` 正确分派到
 * `WechatOpenApp::authorizer()->call('/pay/unifiedorder', ...)`（代授权方下单）。
 *
 * 覆盖：
 *  - 适配器可被 Union 解析，channel 为 WechatApp；
 *  - unifiedOrder 校验 authorizer_access_token / authorizer_appid（缺失抛 InvalidArgumentException）；
 *  - unifiedOrder 真实分派到底层 Authorizer::call，返回预下单响应（prepay_id）；
 *  - 静态门面 Union::instance()->pay(Channel::WechatApp) 同样可用。
 *
 * HTTP 层以 {@see FakeHttpClient} 桩替换，避免真实请求。
 */
final class WechatAppPayAdapterTest extends TestCase
{
    private function buildUnion(): Union
    {
        $http = (new FakeHttpClient())
            ->stub(
                'pay/unifiedorder',
                ['return_code' => 'SUCCESS', 'prepay_id' => 'wx_prepay_xyz'],
            );

        $kernel = new Kernel(
            [
                'wechat_open' => [
                    'app_id'           => 'wx_open_app',
                    'secret'           => 'open-secret',
                    'component_app_id' => 'comp_appid',
                    'component_secret' => 'comp_secret',
                    'cache'            => new ArrayCache(),
                ],
            ],
            $http,
        );

        return $kernel->union();
    }

    public function testWechatAppPayAdapterIsResolved(): void
    {
        $pay = $this->buildUnion()->pay(Channel::WechatApp);

        self::assertInstanceOf(PayAdapter::class, $pay);
        self::assertSame(Channel::WechatApp, $pay->channel());
    }

    public function testWechatAppPayUnifiedOrderDispatchesToAuthorizer(): void
    {
        $result = $this->buildUnion()->pay(Channel::WechatApp)->unifiedOrder([
            'authorizer_access_token' => 'AUTH_TOK',
            'authorizer_appid'        => 'auth_appid_1',
            'out_trade_no'            => 'ORDER_WXAPP_1',
            'body'                    => '商品',
            'total_fee'               => 100,
            'openid'                  => 'OPENID_1',
        ]);

        // 底层 Authorizer::call 被调用并返回解析后的预下单响应
        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertSame('wx_prepay_xyz', $result['prepay_id'] ?? null);
    }

    public function testWechatAppPayRequiresAuthorizerCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('App 支付需传入 authorizer_access_token / authorizer_appid');

        $this->buildUnion()->pay(Channel::WechatApp)->unifiedOrder([
            'out_trade_no' => 'ORDER_WXAPP_2',
            'total_fee'    => 100,
        ]);
    }

    public function testWechatAppPayViaStaticFacade(): void
    {
        // Kernel 构造时已注入全局 Kernel，静态门面可直接使用
        self::assertSame(Channel::WechatApp, Union::instance()->pay(Channel::WechatApp)->channel());
    }
}
