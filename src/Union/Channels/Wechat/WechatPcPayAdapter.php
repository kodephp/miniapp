<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Union\Channel;

/**
 * 微信 PC 扫码支付适配器（trade_type = NATIVE）
 *
 * 复用与公众号 / 小程序完全一致的 V3 下单与签名链路，
 * 仅交易类型与端点不同（/pay/transactions/native）。
 * 微信在响应中返回 code_url，前端据此生成二维码。
 */
final class WechatPcPayAdapter extends WechatPayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatPc;
    }

    #[\Override]
    protected function tradeType(): string
    {
        return 'NATIVE';
    }
}
