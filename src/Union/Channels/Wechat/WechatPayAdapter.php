<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * 微信支付适配器（公众号 + 小程序）
 *
 * 业务侧调用：
 *   $result = $kernel->union()->pay(Channel::WechatMini)->unifiedOrder([
 *       'out_trade_no' => 'xxx',
 *       'body'         => '商品',
 *       'total_fee'    => 100,  // 单位：分
 *       'openid'       => 'USER_OPENID',
 *   ]);
 */
final class WechatPayAdapter extends BaseAdapter implements PayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatMini;
    }

    #[\Override]
    public function unifiedOrder(array $order): array
    {
        $provider = $this->provider('wechat');
        $app      = $provider->app();
        if (!$app instanceof WechatApp) {
            throw new \RuntimeException('微信支付要求 wechat Provider');
        }

        $pay = $app->pay();

        /** @var array<string, mixed> $result */
        $result = $pay->order($order);
        return $result;
    }
}
