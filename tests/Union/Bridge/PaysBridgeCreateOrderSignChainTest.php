<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 真实下单端到端（签名拼装全链路）验证。
 *
 * 2.0 起 kode/pays 为唯一支付路径且为硬依赖（vendor 已安装真实 kode/pays）。
 * 本测试通过 {@see FakePaysHttpClient} 注入真实网关实例，走通「参数校验 / 签名 / 报文拼装 /
 * 响应解析」真实代码路径而不触网，并额外断言：
 *  - 真实网关对出站请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 重新校验通过；
 *  - JSAPI 缺 openid 时网关在参数校验阶段即抛错（而非静默下行）；
 *  - 经真实 Kernel resolver 的 PlatformUnion::pay() 门面同样产出已签名请求并返回 prepay_id。
 *
 * 这是「签名拼装全链路」的闭环证据：桥接只负责注入付款人身份，签名与报文由真实 kode/pays 网关完成。
 */
final class PaysBridgeCreateOrderSignChainTest extends TestCase
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
     * 将微信 V2 出站 XML 解析为关联数组（剥离 CDATA，数值保持字符串形态）。
     *
     * @return array<string, string>
     */
    private function parseWechatXml(string $xml): array
    {
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);
        $arr = json_decode((string) json_encode($doc), true);

        return is_array($arr) ? $arr : [];
    }

    public function testCreateOrderSignsOutboundRequestViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->createOrder(
            ['out_trade_no' => 'T20260815001', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI'],
            $this->user(Channel::WechatMini, 'OPEN_SIGN'),
        );

        // 真实网关解析成功响应
        self::assertSame('WXPREPAY_1', $result['prepay_id']);
        self::assertStringContainsString('api.mch.weixin.qq.com/pay/unifiedorder', $this->fake->lastUrl ?? '');

        // 出站请求体为合法 XML
        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向微信发送 XML 报文');

        $req = $this->parseWechatXml($body);

        // 桥接注入的付款人 openid 必须进入出站请求并参与签名
        self::assertSame('OPEN_SIGN', $req['openid'] ?? '', '付款人 openid 注入后必须参与签名');

        // 全链路核心：真实网关必须对出站请求做了 MD5 签名，且签名可用同一 api_key 重算校验通过
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站请求签名必须可用 api_key 重新校验通过（验证网关签名拼装完整）',
        );
    }

    public function testGatewayRejectsJsapiOrderWithoutOpenid(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // JSAPI 必须提供 openid；网关在参数校验阶段即抛错（不静默下行）
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('openid');

        $adapter->createOrder(
            ['out_trade_no' => 'T_NO_OPENID', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI'],
            null,
        );
    }

    public function testCreateOrderViaRealKernelResolverThroughPlatformUnionFacade(): void
    {
        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id'        => 'wx_app',
                    'secret'        => 'wechat-secret',
                    'mch_id'        => 'mch_1',
                    'mch_serial_no' => 'test_serial_no',
                    'key'           => self::API_KEY,
                    'key_path'      => $this->writeTempKey(),
                ],
            ],
            new FakeHttpClient(),
        );
        $kernel->union();

        $pay = $kernel->union()->wechat()->pay();
        self::assertInstanceOf(PaysBridgePayAdapter::class, $pay);

        $result = $pay->createOrder(
            ['out_trade_no' => 'T_FACADE_1', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI'],
            $this->user(Channel::WechatMini, 'OPEN_FACADE'),
        );

        self::assertSame('WXPREPAY_1', $result['prepay_id']);

        $req = $this->parseWechatXml((string) ($this->fake->lastRawBody ?? ''));
        self::assertSame('OPEN_FACADE', $req['openid'] ?? '');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '门面级下单同样必须产出已签名请求',
        );
    }

    private function writeTempKey(): string
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        \assert($res !== false);
        openssl_pkey_export($res, $key);
        $file = tempnam(sys_get_temp_dir(), 'wxkey') . '.pem';
        file_put_contents($file, $key);

        return $file;
    }
}
