<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Alipay;

use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;

/**
 * 支付宝回调适配器
 */
final class AlipayNotifyAdapter extends BaseAdapter implements NotifyAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::AlipayMini;
    }

    #[\Override]
    public function decode(array $payload, array $headers = []): array
    {
        return [
            'out_trade_no' => $payload['out_trade_no'] ?? '',
            'trade_no'     => $payload['trade_no'] ?? '',
            'total_amount' => $payload['total_amount'] ?? '',
            'trade_status' => $payload['trade_status'] ?? '',
            'raw'          => $payload,
        ];
    }
}
