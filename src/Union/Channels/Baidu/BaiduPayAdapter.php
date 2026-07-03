<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Baidu;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Baidu\BaiduApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * 百度支付适配器（小程序）
 */
final class BaiduPayAdapter extends BaseAdapter implements PayAdapter
{
    public function channel(): Channel
    {
        return Channel::BaiduMini;
    }

    public function unifiedOrder(array $order): array
    {
        $provider = $this->provider('baidu');
        $app      = $provider->app();
        if (!$app instanceof BaiduApp) {
            throw new \RuntimeException('百度支付要求 baidu Provider');
        }

        $pay = $app->pay();
        if (!method_exists($pay, 'createOrder')) {
            throw new \RuntimeException('百度支付模块未提供 createOrder 方法');
        }

        /** @var array<string, mixed> $result */
        $result = $pay->createOrder($order);
        return $result;
    }
}
