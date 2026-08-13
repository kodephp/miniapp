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
 * 抖音支付适配器（Union 层接线）回归测试
 *
 * 验证 `Union::douyin()->pay()->unifiedOrder([...])` 正确分派到 `Douyin\Modules\Pay::create()`。
 *
 * ⚠️ 回归背景：适配器曾误调用不存在的 `createOrder()`（底层 Pay 模块方法名为 `create()`），
 * 导致抖音支付在 Union 层运行时抛「未提供 createOrder 方法」。本测试锁定该修复，
 * 确保 unifiedOrder 真实走通预下单并返回平台原始响应。
 * HTTP 层以 {@see FakeHttpClient} 桩替换，避免真实请求。
 */
final class DouyinPayAdapterTest extends TestCase
{
    private function buildUnion(): Union
    {
        $http = (new FakeHttpClient())
            ->stub('create_order', [
                'err_no'   => 0,
                'err_tips' => '',
                'data'     => ['order_id' => 'DID_123', 'order_token' => 'DTOK'],
            ]);

        $kernel = new Kernel(
            [
                'douyin' => [
                    'app_id'     => 'douyinapp00000000',
                    'secret'     => 'douyin-secret',
                    'salt'       => 'douyin-salt',
                    'token'      => 'douyin-token',
                    'notify_url' => 'https://example.com/notify',
                    'cache'      => new ArrayCache(),
                ],
            ],
            $http,
        );

        return $kernel->union();
    }

    public function testDouyinPayAdapterIsResolved(): void
    {
        $pay = $this->buildUnion()->douyin()->pay();

        self::assertInstanceOf(PayAdapter::class, $pay);
        self::assertSame(Channel::DouyinMini, $pay->channel());
    }

    public function testDouyinPayUnifiedOrderDispatchesToProviderCreate(): void
    {
        $result = $this->buildUnion()->douyin()->pay()->unifiedOrder([
            'out_order_no' => 'D_ORDER_1',
            'total_amount' => 100,
            'subject'      => '商品',
            'body'         => 'desc',
        ]);

        // 修复前会抛 RuntimeException「抖音支付模块未提供 createOrder 方法」；
        // 修复后正确分派到 Douyin\Modules\Pay::create()，返回预下单响应。
        self::assertIsArray($result);
        self::assertSame(0, $result['err_no'] ?? null);
        self::assertSame('DID_123', $result['data']['order_id'] ?? null);
    }
}
