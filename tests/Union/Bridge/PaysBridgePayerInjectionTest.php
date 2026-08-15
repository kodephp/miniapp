<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgeNotifyAdapter;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionUser;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 验证 PaysBridge 把 miniapp 登录得到的付款人身份翻译注入 kode/pays 原生字段。
 *
 * 2.0 起 kode/pays 为唯一支付路径且为硬依赖（vendor 已安装真实 kode/pays）。
 * 本测试通过 {@see FakePaysHttpClient} 注入真实网关实例，走通「参数校验 / 签名 / 报文拼装 /
 * 响应解析」真实代码路径而不触网，并断言：
 *  - 微信 JSAPI：付款人 openid 进入发给微信的 XML 请求体；
 *  - QQ JSAPI：付款人 openid 进入发给 QQ 的 JSON 请求体；
 *  - 支付宝：下单分发到支付宝并返回合法跳转结构（page.pay 不转发 buyer_id，属 pays 网关行为）；
 *  - 抖音：下单分发到抖音并返回成功响应。
 */
final class PaysBridgePayerInjectionTest extends TestCase
{
    /**
     * 微信 V2 响应用于验签的 api_key（须与传给网关的 config.api_key 完全一致）
     */
    private const API_KEY = 'unit_test_api_key_0123456789';

    private FakePaysHttpClient $fake;

    protected function setUp(): void
    {
        $this->fake = new FakePaysHttpClient(self::API_KEY);
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    private function user(Channel $channel, string $openId): UnionUser
    {
        return new UnionUser(unionId: '', openId: $openId, channel: $channel);
    }

    /**
     * @return array<string, mixed>
     */
    private function wechatConfig(): array
    {
        return ['app_id' => 'wx_app', 'mch_id' => 'mch_1', 'api_key' => self::API_KEY];
    }

    /**
     * @return array<string, mixed>
     */
    private function qqConfig(): array
    {
        if ($this->qqPrivateKey === null) {
            $res = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'digest_alg'       => 'sha256',
                'bits'             => 2048,
            ]);
            \assert($res !== false);
            openssl_pkey_export($res, $key);
            $this->qqPrivateKey = $key;
            $this->qqSerial     = 'TEST_SERIAL_' . md5((string) random_int(0, 999999));
        }

        return [
            'app_id'      => 'qq_app',
            'mch_id'      => 'qq_mch',
            'api_key'     => self::API_KEY,
            'serial_no'   => $this->qqSerial,
            'private_key' => $this->qqPrivateKey,
        ];
    }

    private ?string $qqPrivateKey = null;

    private ?string $qqSerial = null;

    private ?string $alipayPrivateKey = null;

    /**
     * @return array<string, mixed>
     */
    private function alipayConfig(): array
    {
        if ($this->alipayPrivateKey === null) {
            $res = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'digest_alg'       => 'sha256',
                'bits'             => 2048,
            ]);
            \assert($res !== false);
            openssl_pkey_export($res, $key);
            $this->alipayPrivateKey = $key;
        }

        return [
            'app_id'      => 'al_app',
            'private_key' => $this->alipayPrivateKey,
            'public_key'  => 'y',
        ];
    }

    public function testInjectsWechatOpenidFromUnionUser(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->createOrder(
            ['out_trade_no' => 'T20260814001', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI'],
            $this->user(Channel::WechatMini, 'OPEN_ABC'),
        );

        // 真实网关解析成功响应，返回 prepay_id
        self::assertSame('WXPREPAY_1', $result['prepay_id']);
        // 付款人 openid 必须进入发给微信的 XML 请求体（桥接注入的核心价值）
        self::assertStringContainsString('<openid><![CDATA[OPEN_ABC]]></openid>', $this->fake->lastRawBody ?? '');
        self::assertStringContainsString('api.mch.weixin.qq.com/pay/unifiedorder', $this->fake->lastUrl ?? '');
    }

    public function testDoesNotOverrideExplicitlyProvidedOpenid(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $adapter->createOrder(
            ['out_trade_no' => 'T1', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI', 'openid' => 'EXISTING'],
            $this->user(Channel::WechatMini, 'OPEN_ABC'),
        );

        self::assertStringContainsString('<openid><![CDATA[EXISTING]]></openid>', $this->fake->lastRawBody ?? '');
    }

    public function testInjectsQqOpenidFromUnionUser(): void
    {
        $adapter = PaysBridge::adapter(Channel::Qq, fn () => $this->qqConfig());

        $result = $adapter->createOrder(
            ['out_trade_no' => 'T_QQ', 'total_amount' => 1, 'trade_type' => 'JSAPI'],
            $this->user(Channel::Qq, 'QQ_OPENID_1'),
        );

        self::assertSame('QQPREPAY_1', $result['prepay_id']);
        // 付款人 openid 必须进入发给 QQ 的 JSON 请求体（V3 报文）
        self::assertStringContainsString('"openid":"QQ_OPENID_1"', $this->fake->lastRawBody ?? '');
    }

    public function testInjectsAlipayBuyerIdDoesNotBreakDispatch(): void
    {
        // 支付宝 page.pay 直接返回跳转 URL、不转发 buyer_id（kode/pays 网关行为），
        // 桥接仍负责注入；此处断言下单成功分发到支付宝且返回合法跳转结构。
        $adapter = PaysBridge::adapter(Channel::AlipayMini, fn () => $this->alipayConfig());

        $result = $adapter->createOrder(
            ['out_trade_no' => 'T2', 'total_amount' => '0.01', 'subject' => 's'],
            $this->user(Channel::AlipayMini, 'ALI_UID_9'),
        );

        self::assertSame('GET', $result['method']);
        self::assertStringContainsString('gateway.do', $result['url']);
        self::assertStringContainsString('out_trade_no', $result['url']);
    }

    public function testThrowsWhenPayerChannelMismatchesPaymentChannel(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不属于同一平台');

        $adapter->createOrder(
            ['out_trade_no' => 'T3', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI'],
            $this->user(Channel::AlipayMini, 'ALI_UID'),
        );
    }

    public function testNullUserLeavesOrderUntouched(): void
    {
        $adapter = PaysBridge::adapter(Channel::Qq, fn () => $this->qqConfig());

        $result = $adapter->createOrder(
            ['out_trade_no' => 'T4', 'total_amount' => 1, 'trade_type' => 'NATIVE'],
            null,
        );

        self::assertArrayNotHasKey('openid', $result);
    }

    public function testDouyinChannelHasNoPayerInjection(): void
    {
        // 抖音 / 百度等渠道 pays 原生下单不依赖 openid，桥接不做付款人注入
        $adapter = PaysBridge::adapter(Channel::DouyinMini, fn () => [
            'app_id'      => 'tt_app',
            'merchant_id' => 'tt_mch',
            'salt'        => 'tt_salt',
        ]);

        $result = $adapter->createOrder(
            ['out_order_no' => 'T_DOUYIN', 'total_amount' => 1, 'subject' => 's', 'body' => 'b', 'valid_time' => 300],
            $this->user(Channel::DouyinMini, 'DOUYIN_UID'),
        );

        self::assertSame('DYPREPAY_1', $result['prepay_id']);
        self::assertStringContainsString('toutiao.com', $this->fake->lastUrl ?? '');
    }

    public function testVerifyNotifyBridgesToPays(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 微信 V2 回调验签：用同一 api_key 构造合法签名载荷，桥接委托 pays verifyNotify 验签后原样返回
        $data            = ['out_trade_no' => 'T', 'transaction_id' => 'WX1', 'result_code' => 'SUCCESS'];
        $data['sign']    = Signer::md5($data, self::API_KEY);

        $result = $adapter->verifyNotify($data);

        self::assertSame('T', $result['out_trade_no']);
    }

    public function testVerifyNotifyThrowsOnBadSignature(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $data = ['out_trade_no' => 'T', 'transaction_id' => 'WX1', 'result_code' => 'SUCCESS', 'sign' => 'deadbeef'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('验签失败');
        $adapter->verifyNotify($data);
    }

    /**
     * 验证「安装 kode/pays 后，Union::pay() 返回 pays 桥接适配器」这一 2.0 核心体验：
     * 业务侧仅需 composer require kode/pays，调用代码（pay()->createOrder）与 pays 网关契约一致。
     */
    public function testUnionPayReturnsPaysBridgeAdapterWhenInstalled(): void
    {
        $pay = $this->buildUnion()->pay(Channel::WechatMini);

        self::assertInstanceOf(PaysBridgePayAdapter::class, $pay);

        // 方法名与 pays 网关契约一致：createOrder；付款人身份由桥接自动注入
        $result = $pay->createOrder(
            ['out_trade_no' => 'T_AUTO', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI'],
            $this->user(Channel::WechatMini, 'O_AUTO'),
        );

        self::assertSame('WXPREPAY_1', $result['prepay_id']);
    }

    /**
     * 验证「回调验签」同样走 pays 桥接：Union::notify() 返回 PaysBridgeNotifyAdapter，
     * 其 decode() 委托 pays 验签。
     */
    public function testUnionNotifyReturnsBridgeAdapter(): void
    {
        $adapter = $this->buildUnion()->notify(Channel::WechatMini);

        self::assertInstanceOf(PaysBridgeNotifyAdapter::class, $adapter);
        self::assertSame(Channel::WechatMini, $adapter->channel());

        $data            = ['out_trade_no' => 'T', 'transaction_id' => 'WX1', 'result_code' => 'SUCCESS'];
        $data['sign']    = Signer::md5($data, self::API_KEY);

        $result = $adapter->decode($data);

        self::assertSame('T', $result['out_trade_no']);
    }

    /**
     * 2.0 起默认 Kernel resolver 已覆盖抖音渠道，故 douyin 下单直接返回 pays 桥接适配器。
     */
    public function testUnionPayReturnsPaysBridgeForCoveredDouyinChannel(): void
    {
        $pay = $this->buildUnion()->pay(Channel::DouyinMini);

        self::assertInstanceOf(PaysBridgePayAdapter::class, $pay);
        self::assertSame(Channel::DouyinMini, $pay->channel());
    }

    /**
     * 默认 Kernel resolver 未覆盖的渠道（如百度）——kode/pays 网关注册表中也无 baidu，
     * 故下单会在网关创建阶段抛清晰异常，引导业务侧接入 pays 对应网关或自定义 resolver。
     */
    public function testCustomResolverBaiduThrowsUnsupportedGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::BaiduMini, static fn () => [
            'app_id' => 'b_app',
        ]);

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('baidu');

        $adapter->createOrder(['out_trade_no' => 'T_BAIDU', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI']);
    }

    private function buildUnion(): Union
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        \assert($res !== false);
        openssl_pkey_export($res, $key);
        $keyFile = tempnam(sys_get_temp_dir(), 'wxkey') . '.pem';
        file_put_contents($keyFile, $key);

        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id'        => 'wx_app',
                    'secret'        => 'wechat-secret',
                    'mch_id'        => 'wechat_mch',
                    'mch_serial_no' => 'test_serial_no',
                    'key'           => self::API_KEY,
                    'key_path'      => $keyFile,
                    'cache'         => new ArrayCache(),
                ],
                'douyin' => [
                    'app_id' => 'tt_app',
                    'secret' => 'douyin-secret',
                    'salt'   => 'douyin-salt',
                ],
            ],
            new FakeHttpClient(),
        );

        return $kernel->union();
    }
}
