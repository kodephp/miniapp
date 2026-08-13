<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\WechatWork;

use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;

/**
 * 企业微信回调适配器
 */
final class WeWorkNotifyAdapter extends BaseAdapter implements NotifyAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatWork;
    }

    #[\Override]
    public function decode(array $payload, array $headers = []): array
    {
        return [
            'event_type' => $payload['Event'] ?? '',
            'raw'        => $payload,
        ];
    }
}
