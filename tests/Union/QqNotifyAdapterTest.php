<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;

/**
 * QQ 回调适配器（Union 层接线）测试
 *
 * 验证 Union::qq()->notify() 不再抛「不支持回调」，且 decode() 正确归一化。
 */
final class QqNotifyAdapterTest extends TestCase
{
    private function buildUnion(): Union
    {
        $kernel = new Kernel(
            ['qq' => [
                'app_id' => 'qqapp000000000000',
                'secret' => 'qq-secret',
                'mch_id' => 'qq-mch',
                'api_key' => 'qq-api-key',
                'cache'  => new ArrayCache(),
            ]],
            new FakeHttpClient(),
        );
        $kernel->union();

        return $kernel->union();
    }

    public function testQqNotifyAdapterIsResolved(): void
    {
        $notify = $this->buildUnion()->qq()->notify();

        self::assertInstanceOf(NotifyAdapter::class, $notify);
        self::assertSame(Channel::Qq, $notify->channel());
    }

    public function testQqNotifyDecodeNormalizes(): void
    {
        $result = $this->buildUnion()->qq()->notify()->decode([
            'out_trade_no'  => 'ORDER_1',
            'transaction_id' => 'TXN_1',
            'total_fee'     => '100',
            'openid'        => 'OPENID_1',
            'result_code'   => 'SUCCESS',
        ]);

        self::assertSame('ORDER_1', $result['out_trade_no']);
        self::assertSame('TXN_1', $result['transaction_id']);
        self::assertSame(100, $result['total_fee']);
        self::assertSame('OPENID_1', $result['openid']);
        self::assertSame('SUCCESS', $result['result_code']);
        self::assertArrayHasKey('raw', $result);
    }
}
