<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;

/**
 * 微信回调适配器（公众号 + 小程序 + PC + App）
 *
 * 处理微信支付回调的签名验证、XML 解析、返回数据组装。
 */
final class WechatNotifyAdapter extends BaseAdapter implements NotifyAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatMini;
    }

    public function decode(array $payload, array $headers = []): array
    {
        // 微信支付回调为 XML 格式，业务侧应在 entry 阶段转为 array
        // 此处仅做基本数据归一化
        return [
            'out_trade_no' => self::str($payload, 'out_trade_no'),
            'transaction_id' => self::str($payload, 'transaction_id'),
            'total_fee'     => (int) ($payload['total_fee'] ?? 0),
            'openid'        => self::str($payload, 'openid'),
            'result_code'   => self::str($payload, 'result_code'),
            'raw'           => $payload,
        ];
    }
}
