<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Qq;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Qq\QqApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * QQ 支付适配器（小程序）
 *
 * 复用底层 {@see QqApp::pay()}（已完整实现 unifiedOrder / orderQuery / closeOrder / refund），
 * 仅做统一下单入口的适配，使 `Union::qq()->pay()->unifiedOrder([...])` 与其他端一致。
 */
final class QqPayAdapter extends BaseAdapter implements PayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::Qq;
    }

    #[\Override]
    public function unifiedOrder(array $order, ?UnionUser $user = null): array
    {
        $provider = $this->provider('qq');
        $app      = $provider->app();
        if (!$app instanceof QqApp) {
            throw new \RuntimeException('QQ 支付要求 qq Provider');
        }

        $pay = $app->pay();

        /** @var array<string, mixed> $result */
        $result = $pay->unifiedOrder($order);
        return $result;
    }
}
