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
 * 百度支付适配器（Union 层接线）回归测试
 *
 * 验证 `Union::baidu()->pay()->unifiedOrder([...])` 正确分派到 `Baidu\Modules\Pay::create()`。
 *
 * ⚠️ 回归背景：适配器曾误调用不存在的 `createOrder()`（底层 Pay 模块方法名为 `create()`），
 * 导致百度支付在 Union 层运行时抛「未提供 createOrder 方法」。本测试锁定该修复，
 * 确保 unifiedOrder 真实走通预下单并返回平台原始响应。
 * HTTP 层以 {@see FakeHttpClient} 桩替换，避免真实请求。
 */
final class BaiduPayAdapterTest extends TestCase
{
    private function buildUnion(): Union
    {
        $http = (new FakeHttpClient())
            ->stub('oauth/2.0/token', ['access_token' => 'BAIDU_TOKEN', 'expires_in' => 7200])
            ->stub('precreate', ['errno' => 0, 'msg' => 'ok', 'data' => ['orderId' => 'BID_123']]);

        $kernel = new Kernel(
            [
                'baidu' => [
                    'app_id'  => 'baiduapp0000000000',
                    'secret'  => 'baidu-secret',
                    'deal_id' => 'baidu-deal',
                    'cache'   => new ArrayCache(),
                ],
            ],
            $http,
        );

        return $kernel->union();
    }

    public function testBaiduPayAdapterIsResolved(): void
    {
        $pay = $this->buildUnion()->baidu()->pay();

        self::assertInstanceOf(PayAdapter::class, $pay);
        self::assertSame(Channel::BaiduMini, $pay->channel());
    }

    public function testBaiduPayUnifiedOrderDispatchesToProviderCreate(): void
    {
        $result = $this->buildUnion()->baidu()->pay()->unifiedOrder([
            'out_trade_no' => 'B_ORDER_1',
            'total_amount' => 100,
            'subject'      => '商品',
        ]);

        // 修复前会抛 RuntimeException「百度支付模块未提供 createOrder 方法」；
        // 修复后正确分派到 Baidu\Modules\Pay::create()，返回预下单响应。
        self::assertIsArray($result);
        self::assertSame(0, $result['errno'] ?? null);
        self::assertSame('BID_123', $result['data']['orderId'] ?? null);
    }
}
