<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Douyin;

use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;

/**
 * 抖音回调适配器
 */
final class DouyinNotifyAdapter extends BaseAdapter implements NotifyAdapter
{
    public function channel(): Channel
    {
        return Channel::DouyinMini;
    }

    public function decode(array $payload, array $headers = []): array
    {
        return [
            'out_trade_no' => $payload['out_trade_no'] ?? '',
            'trade_no'     => $payload['trade_no'] ?? '',
            'result_code'  => $payload['result_code'] ?? '',
            'raw'          => $payload,
        ];
    }
}
