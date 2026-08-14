<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\CapturingHttpClient;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;

/**
 * 微信 App 支付适配器（Union 层接线）回归测试
 *
 * 验证 `Union::pay(Channel::WechatApp)->unifiedOrder([...])` 正确分派到
 * 微信支付 V3 `/pay/transactions/app` 端点，并自动附加可验签的 Authorization 头。
 *
 * HTTP 层以 {@see CapturingHttpClient} 桩替换，断言实际发出的请求 URI 与签名头。
 */
final class WechatAppPayAdapterTest extends TestCase
{
    private CapturingHttpClient $http;

    private function buildUnion(): Union
    {
        $this->http = new CapturingHttpClient();
        $this->http->stub(['prepay_id' => 'wx_prepay_app']);

        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id'        => 'wx_open_mobile_app',
                    'secret'        => 'wechat-secret',
                    'mch_id'        => 'wechat_mch',
                    'mch_serial_no' => 'serial_no_xyz',
                    'key_path'      => $this->keyFile(),
                    'notify_url'    => 'https://example.com/notify',
                ],
            ],
            $this->http,
        );

        return $kernel->union();
    }

    private function keyFile(): string
    {
        static $file;
        if ($file === null) {
            $res = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'digest_alg'       => 'sha256',
                'bits'             => 2048,
            ]);
            \assert($res !== false);
            openssl_pkey_export($res, $key);
            $file = tempnam(sys_get_temp_dir(), 'wxappkey') . '.pem';
            file_put_contents($file, $key);
        }

        return $file;
    }

    public function testWechatAppPayAdapterIsResolved(): void
    {
        $pay = $this->buildUnion()->pay(Channel::WechatApp);

        self::assertInstanceOf(PayAdapter::class, $pay);
        self::assertSame(Channel::WechatApp, $pay->channel());
    }

    public function testWechatAppPayUnifiedOrderDispatchesToV3AppEndpoint(): void
    {
        $result = $this->buildUnion()->pay(Channel::WechatApp)->unifiedOrder([
            'out_trade_no' => 'ORDER_WXAPP_1',
            'description'  => '商品',
            'amount'       => ['total' => 100],
        ]);

        self::assertSame('wx_prepay_app', $result['prepay_id'] ?? null);

        $req = $this->http->last();
        self::assertSame('POST', $req['method']);
        self::assertStringEndsWith('/pay/transactions/app', $req['uri']);
        self::assertStringStartsWith('WECHATPAY2-SHA256-RSA2048 ', $req['headers']['Authorization']);
    }
}
