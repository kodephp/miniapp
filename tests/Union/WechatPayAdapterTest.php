<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\CapturingHttpClient;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionUser;
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
    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();

        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        \assert($res !== false);
        openssl_pkey_export($res, $key);
        $this->keyFile = tempnam(sys_get_temp_dir(), 'wxkey') . '.pem';
        file_put_contents($this->keyFile, $key);
    }

    protected function tearDown(): void
    {
        if (is_file($this->keyFile)) {
            unlink($this->keyFile);
        }

        parent::tearDown();
    }

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
                    'app_id'        => 'wx_app',
                    'secret'        => 'wechat-secret',
                    'mch_id'        => 'wechat_mch',
                    'mch_serial_no' => 'test_serial_no',
                    'key_path'      => $this->keyFile,
                    'cache'         => new ArrayCache(),
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

    /**
     * 登录与支付强绑定：传入已登录 UnionUser，JSAPI 下单自动注入其 openid。
     */
    public function testUnifiedOrderAutoInjectsOpenidFromLoggedInUser(): void
    {
        $http = new CapturingHttpClient();
        $http->stub(['prepay_id' => 'wx_prepay_inject']);

        $kernel = new Kernel([
            'wechat' => [
                'app_id'        => 'wx_app',
                'secret'        => 'wechat-secret',
                'mch_id'        => 'wechat_mch',
                'mch_serial_no' => 'test_serial_no',
                'key_path'      => $this->keyFile,
                'cache'         => new ArrayCache(),
            ],
        ], $http);
        $union = $kernel->union();

        $user = new UnionUser(unionId: 'u_1', openId: 'OPENID_INJECTED', channel: Channel::WechatMini);

        $result = $union->pay(Channel::WechatMini)->unifiedOrder([
            'out_trade_no' => 'WX_ORDER_INJECT',
            'description'  => '商品',
            'amount'       => ['total' => 100],
        ], $user);

        self::assertSame('wx_prepay_inject', $result['prepay_id'] ?? null);
        $body = json_decode($http->last()['body'], true);
        self::assertSame('OPENID_INJECTED', $body['payer']['openid'] ?? null);
    }

    /**
     * JSAPI 缺 openid 时 fail-fast，抛出清晰异常（避免微信侧含糊报错）。
     */
    public function testUnifiedOrderRequiresOpenidForJsapi(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSAPI 支付必须传入付款人 openid');

        $this->buildUnion()->pay(Channel::WechatMini)->unifiedOrder([
            'out_trade_no' => 'WX_ORDER_NO_OPENID',
            'description'  => '商品',
            'amount'       => ['total' => 100],
        ]);
    }

    /**
     * 便捷写法 Union::wechat()->unifiedOrder($order, user: $user) 透传用户到支付适配器。
     */
    public function testUnionUnifiedOrderForwardsUser(): void
    {
        $http = new CapturingHttpClient();
        $http->stub(['prepay_id' => 'wx_prepay_forward']);

        $kernel = new Kernel([
            'wechat' => [
                'app_id'        => 'wx_app',
                'secret'        => 'wechat-secret',
                'mch_id'        => 'wechat_mch',
                'mch_serial_no' => 'test_serial_no',
                'key_path'      => $this->keyFile,
                'cache'         => new ArrayCache(),
            ],
        ], $http);
        $union = $kernel->union();

        $user = new UnionUser(unionId: 'u_2', openId: 'OPENID_FWD', channel: Channel::WechatMini);

        $result = $union->wechat()->unifiedOrder([
            'out_trade_no' => 'WX_FWD',
            'description'  => '商品',
            'amount'       => ['total' => 100],
        ], user: $user);

        self::assertSame('wx_prepay_forward', $result['prepay_id'] ?? null);
        $body = json_decode($http->last()['body'], true);
        self::assertSame('OPENID_FWD', $body['payer']['openid'] ?? null);
    }
}
