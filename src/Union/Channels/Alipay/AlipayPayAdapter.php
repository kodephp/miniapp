<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Alipay;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * 支付宝支付适配器（小程序 / 生活号 / App 通用）
 */
final class AlipayPayAdapter extends BaseAdapter implements PayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::AlipayMini;
    }

    #[\Override]
    public function unifiedOrder(array $order): array
    {
        $provider = $this->provider('alipay');
        $app      = $provider->app();
        if (!$app instanceof AlipayApp) {
            throw new \RuntimeException('支付宝支付要求 alipay Provider');
        }

        $pay = $app->pay();

        /** @var array<string, mixed> $result */
        $result = $pay->create($order);
        return $result;
    }
}
