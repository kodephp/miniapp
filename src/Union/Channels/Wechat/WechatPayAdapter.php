<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * 微信支付适配器基类（JSAPI：公众号 + 小程序）
 *
 * 统一承载微信生态全部支付端的下单流程，按渠道分派交易类型：
 *  - 公众号 / 小程序 → JSAPI（本类）
 *  - 移动 App        → APP（WechatAppPayAdapter）
 *  - H5              → MWEB（WechatH5PayAdapter）
 *  - PC 扫码         → NATIVE（WechatPcPayAdapter）
 *
 * 全部走微信支付 V3（本包自带签名器），不再区分 V2 / V3 两套机制。
 *
 * 业务侧调用：
 *   $result = $kernel->union()->pay(Channel::WechatMini)->unifiedOrder([
 *       'out_trade_no' => 'xxx',
 *       'body'         => '商品',
 *       'amount'       => ['total' => 100],  // 单位：分
 *       'openid'       => 'USER_OPENID',     // JSAPI 必填
 *   ]);
 */
class WechatPayAdapter extends BaseAdapter implements PayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatMini;
    }

    /**
     * 交易类型（JSAPI / APP / MWEB / NATIVE）
     */
    protected function tradeType(): string
    {
        return 'JSAPI';
    }

    #[\Override]
    public function unifiedOrder(array $order): array
    {
        $provider = $this->provider('wechat');
        $app      = $provider->app();
        if (!$app instanceof WechatApp) {
            throw new \RuntimeException('微信支付要求 wechat Provider');
        }

        /** @var array<string, mixed> $result */
        $result = $app->pay()->order($this->tradeType(), $order);

        return $result;
    }
}
