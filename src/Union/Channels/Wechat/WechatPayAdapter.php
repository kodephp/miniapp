<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\UnionUser;

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
 * 登录与支付强绑定（平台硬约束）：JSAPI（公众号 / 小程序）下单必须携带付款人
 * openid，而 openid 只能来自微信登录（code2session / OAuth），与 appid 强绑定。
 * 因此本适配器支持把已登录的 {@see UnionUser} 直接传入，自动注入 openid，
 * 缺失时 fail-fast 抛出清晰异常，避免微信侧含糊报错：
 *
 *   $user   = Union::wechat()->mini('code');            // 登录拿到 openid
 *   $result = Union::wechat()->pay()->unifiedOrder([
 *       'out_trade_no' => 'xxx',
 *       'body'         => '商品',
 *       'amount'       => ['total' => 100],             // 单位：分
 *   ], $user);                                         // 自动注入 openid
 *
 * 也可显式传 openid（覆盖自动注入）：
 *   ->unifiedOrder(['openid' => 'USER_OPENID', ...])
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
    public function unifiedOrder(array $order, ?UnionUser $user = null): array
    {
        $provider = $this->provider('wechat');
        $app      = $provider->app();
        if (!$app instanceof WechatApp) {
            throw new \RuntimeException('微信支付要求 wechat Provider');
        }

        // JSAPI（公众号 / 小程序）必须绑定付款人 openid，openid 来自微信登录。
        // 兼容业务侧两种写法：顶层 openid 或 payer.openid。
        if ($this->tradeType() === 'JSAPI') {
            /** @var mixed $openId */
            $openId = $order['openid'] ?? ($order['payer']['openid'] ?? null);

            if ($user !== null && (!is_string($openId) || $openId === '')) {
                $order['openid'] = $user->openId;
                $openId           = $user->openId;
            }

            if (!is_string($openId) || $openId === '') {
                throw new \InvalidArgumentException(
                    '微信 JSAPI 支付必须传入付款人 openid（来自微信登录 code2session / OAuth，'
                    . '与当前 appid 强绑定）。请在 unifiedOrder 的 order 中携带 openid，'
                    . '或传入已登录的 UnionUser 自动注入。'
                );
            }
        }

        /** @var array<string, mixed> $result */
        $result = $app->pay()->order($this->tradeType(), $order);

        return $result;
    }
}
