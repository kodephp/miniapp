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
 * 支付宝支付适配器（Union 层接线）回归测试
 *
 * 验证 `Union::pay(Channel::AlipayMini)->unifiedOrder([...])` 正确分派到
 * `Alipay\Modules\Pay::create()`（alipay.trade.create），返回业务响应节点。
 *
 * HTTP 层以 {@see FakeHttpClient} 桩替换，避免真实请求；网关 RSA2 签名所需的
 * RSA 私钥在 setUp 内用 openssl 临时生成（仅用于让网关签名不抛 ConfigException）。
 */
final class AlipayPayAdapterTest extends TestCase
{
    private string $privateKey;

    protected function setUp(): void
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        \assert($res !== false);
        $exported = '';
        openssl_pkey_export($res, $exported);
        $this->privateKey = $exported;
    }

    private function buildUnion(): Union
    {
        $http = (new FakeHttpClient())
            ->stub('gateway.do', [
                'alipay_trade_create_response' => [
                    'code'         => '10000',
                    'msg'          => 'Success',
                    'trade_no'     => 'ALI_TRADE_1',
                    'out_trade_no' => 'ALI_ORDER_1',
                ],
                'sign' => 'stub_sign',
            ]);

        $kernel = new Kernel(
            [
                'alipay' => [
                    'app_id'      => 'alipay_app',
                    'secret'      => 'alipay-secret',
                    'private_key' => $this->privateKey,
                    'cache'       => new ArrayCache(),
                ],
            ],
            $http,
        );

        return $kernel->union();
    }

    public function testAlipayPayAdapterIsResolved(): void
    {
        $pay = $this->buildUnion()->pay(Channel::AlipayMini);

        self::assertInstanceOf(PayAdapter::class, $pay);
        self::assertSame(Channel::AlipayMini, $pay->channel());
    }

    public function testAlipayPayUnifiedOrderDispatchesToProviderCreate(): void
    {
        $result = $this->buildUnion()->pay(Channel::AlipayMini)->unifiedOrder([
            'out_trade_no' => 'ALI_ORDER_1',
            'total_amount' => '0.01',
            'subject'      => '商品',
            'buyer_id'     => 'BUYER_1',
        ]);

        // 底层 Alipay\Modules\Pay::create() 被调用，返回 alipay_trade_create_response 节点
        self::assertSame('10000', $result['code'] ?? null);
        self::assertSame('ALI_TRADE_1', $result['trade_no'] ?? null);
        self::assertSame('ALI_ORDER_1', $result['out_trade_no'] ?? null);
    }
}
