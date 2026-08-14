<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Union\Channel;

/**
 * 微信 H5 支付适配器（trade_type = MWEB）
 *
 * 复用与公众号 / 小程序完全一致的 V3 下单与签名链路，
 * 仅交易类型与端点不同（/pay/transactions/h5）。
 * 实际支付跳转地址由微信在响应中返回（h5_url）。
 */
final class WechatH5PayAdapter extends WechatPayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatH5;
    }

    #[\Override]
    protected function tradeType(): string
    {
        return 'MWEB';
    }
}
