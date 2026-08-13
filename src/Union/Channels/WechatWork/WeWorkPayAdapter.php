<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\WechatWork;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * 企业微信支付适配器
 */
final class WeWorkPayAdapter extends BaseAdapter implements PayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatWork;
    }

    #[\Override]
    public function unifiedOrder(array $order): array
    {
        $provider = $this->provider('wechatWork');
        $app      = $provider->app();
        if (!$app instanceof WechatWorkApp) {
            throw new \RuntimeException('企业微信支付要求 wechat_work Provider');
        }

        // 企业微信支付能力按架构约定交由 kode/pays 承载（本包支付适配器仅保留下单入口），
        // 当前 wechatWork 渠道未内置支付模块，请使用 kode/pays 或 wechat 主 Provider 完成下单。
        throw new \RuntimeException('企业微信支付暂未实现，请使用 kode/pays 或 wechat 主 Provider 完成下单');
    }
}
