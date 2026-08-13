<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Douyin;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Douyin\DouyinApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * 抖音支付适配器（小程序）
 */
final class DouyinPayAdapter extends BaseAdapter implements PayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::DouyinMini;
    }

    #[\Override]
    public function unifiedOrder(array $order): array
    {
        $provider = $this->provider('douyin');
        $app      = $provider->app();
        if (!$app instanceof DouyinApp) {
            throw new \RuntimeException('抖音支付要求 douyin Provider');
        }

        $pay = $app->pay();

        /** @var array<string, mixed> $result */
        $result = $pay->create($order);
        return $result;
    }
}
