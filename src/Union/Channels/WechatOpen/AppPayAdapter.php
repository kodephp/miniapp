<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\WechatOpen;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;

/**
 * 微信 App 支付适配器（开放平台移动应用）
 */
final class AppPayAdapter extends BaseAdapter implements PayAdapter
{
    #[\Override]
    public function channel(): Channel
    {
        return Channel::WechatApp;
    }

    #[\Override]
    public function unifiedOrder(array $order): array
    {
        $provider = $this->provider('wechatOpen');
        $app      = $provider->app();
        if (!$app instanceof WechatOpenApp) {
            throw new \RuntimeException('App 支付要求 wechat_open Provider');
        }

        $authorizer = $app->authorizer();
        $authorizerAccessToken = is_string($order['authorizer_access_token'] ?? null)
            ? $order['authorizer_access_token']
            : '';
        $authorizerAppId       = is_string($order['authorizer_appid'] ?? null)
            ? $order['authorizer_appid']
            : '';

        if ($authorizerAccessToken === '' || $authorizerAppId === '') {
            throw new \InvalidArgumentException('App 支付需传入 authorizer_access_token / authorizer_appid');
        }

        unset($order['authorizer_access_token'], $order['authorizer_appid']);

        return $authorizer->call(
            authorizerAccessToken: $authorizerAccessToken,
            path: '/pay/unifiedorder',
            params: array_merge(['appid' => $authorizerAppId], $order),
            method: 'POST',
        );
    }
}
