<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\CapturingHttpClient;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;

/**
 * 微信全端支付适配器分派测试
 *
 * 验证 Union::pay(Channel) 对微信生态各端（小程序 / 公众号 / App / H5 / PC）
 * 均能正确解析，并分派到对应的 V3 交易类型端点：
 *  JSAPI（小程序、公众号）/ APP（移动应用）/ MWEB（H5）/ NATIVE（PC 扫码）。
 *
 * 旧实现中 H5 / PC 直接抛「不支持支付」、App 走的是 V2 第三方平台路径，
 * 本测试确保统一到 V3 后各端均可用。
 */
final class WechatPayAdaptersTest extends TestCase
{
    private CapturingHttpClient $http;

    private function buildUnion(): Union
    {
        $this->http = new CapturingHttpClient();
        $this->http->stub(['prepay_id' => 'prepay_x']);

        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id'        => 'wxapp0000000000',
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
            $file = tempnam(sys_get_temp_dir(), 'wxkey') . '.pem';
            file_put_contents($file, $key);
        }

        return $file;
    }

    /**
     * @return array<array{Channel, non-empty-string}>
     */
    public static function channelProvider(): array
    {
        return [
            '小程序 JSAPI' => [Channel::WechatMini, '/pay/transactions/jsapi'],
            '公众号 JSAPI' => [Channel::WechatMp, '/pay/transactions/jsapi'],
            'App APP'     => [Channel::WechatApp, '/pay/transactions/app'],
            'H5 MWEB'     => [Channel::WechatH5, '/pay/transactions/h5'],
            'PC NATIVE'   => [Channel::WechatPc, '/pay/transactions/native'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channelProvider')]
    public function testEachWechatChannelDispatchesToCorrectV3Endpoint(Channel $channel, string $endpoint): void
    {
        $order = [
            'out_trade_no' => 'ORDER_' . $channel->value,
            'description'  => '商品',
            'amount'       => ['total' => 100],
        ];
        // JSAPI（小程序 / 公众号）下单必须绑定付款人 openid（来自微信登录）
        if ($channel === Channel::WechatMini || $channel === Channel::WechatMp) {
            $order['payer'] = ['openid' => 'OPENID_TEST'];
        }

        $this->buildUnion()->pay($channel)->unifiedOrder($order);

        $req = $this->http->last();
        self::assertTrue(str_ends_with($req['uri'], $endpoint), "请求 URI 应以 {$endpoint} 结尾");
        self::assertStringStartsWith('WECHATPAY2-SHA256-RSA2048 ', $req['headers']['Authorization']);
    }
}
