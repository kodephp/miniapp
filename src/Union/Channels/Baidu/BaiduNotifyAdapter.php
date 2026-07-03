<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Baidu;

use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;

/**
 * 百度回调适配器
 */
final class BaiduNotifyAdapter extends BaseAdapter implements NotifyAdapter
{
    public function channel(): Channel
    {
        return Channel::BaiduMini;
    }

    public function decode(array $payload, array $headers = []): array
    {
        return [
            'out_trade_no' => $payload['out_trade_no'] ?? '',
            'trade_no'     => $payload['trade_no'] ?? '',
            'status'       => $payload['status'] ?? '',
            'raw'          => $payload,
        ];
    }
}
