<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Union\Channel;

/**
 * 微信 App 支付适配器（移动应用，trade_type = APP）
 *
 * 复用与公众号 / 小程序完全一致的 V3 下单与签名链路，
 * 仅交易类型与端点不同（/pay/transactions/app）。
 *
 * 注意：App 支付下单的 appid 应为「微信开放平台移动应用」的 AppID，
 * 与公众号 / 小程序 AppID 不同，可在 order 参数中显式传入 appid 覆盖默认值。
 */
final class WechatAppPayAdapter extends WechatPayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatApp;
    }

    #[\Override]
    protected function tradeType(): string
    {
        return 'APP';
    }
}
