<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Baidu;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Baidu\BaiduApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 百度支付适配器（小程序）
 */
final class BaiduPayAdapter extends BaseAdapter implements PayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::BaiduMini;
    }

    #[\Override]
    public function unifiedOrder(array $order, ?UnionUser $user = null): array
    {
        $provider = $this->provider('baidu');
        $app      = $provider->app();
        if (!$app instanceof BaiduApp) {
            throw new \RuntimeException('百度支付要求 baidu Provider');
        }

        $pay = $app->pay();

        /** @var array<string, mixed> $result */
        $result = $pay->create($order);
        return $result;
    }
}
