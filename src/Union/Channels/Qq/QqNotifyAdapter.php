<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Qq;

use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;

/**
 * QQ 回调适配器
 *
 * 处理 QQ 支付回调的数据归一化。XML 已由业务侧 / {@see \Kode\MiniApp\Providers\Qq\Modules\Notify}
 * 解析为 array，此处仅做字段归一化（与微信回调适配器保持一致的结构）。
 */
final class QqNotifyAdapter extends BaseAdapter implements NotifyAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::Qq;
    }

    #[\Override]
    public function decode(array $payload, array $headers = []): array
    {
        return [
            'out_trade_no'  => self::str($payload, 'out_trade_no'),
            'transaction_id' => self::str($payload, 'transaction_id'),
            'total_fee'     => (int) ($payload['total_fee'] ?? 0),
            'openid'        => self::str($payload, 'openid'),
            'result_code'   => self::str($payload, 'result_code'),
            'raw'           => $payload,
        ];
    }
}
