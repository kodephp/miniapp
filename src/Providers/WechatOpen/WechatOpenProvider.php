<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\BaseProvider;
use Kode\MiniApp\Core\HttpClient;
use Kode\MiniApp\Providers\Wechat\WechatProvider;
use Kode\MiniApp\Providers\WechatOpen\Events\OpenPlatformEvent;

/**
 * 微信开放平台 Provider
 *
 * 微信开放平台是连接微信生态（小程序、公众号、移动应用、网站应用）的入口，
 * 第三方平台可通过开放平台代公众号 / 小程序实现业务。
 */
final class WechatOpenProvider extends BaseProvider
{
    /** @var array<string, WechatOpenApp> */
    private array $apps = [];

    private WechatOpenConfig $wechatOpenConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new HttpClient(), $kernel);
        $this->wechatOpenConfig = new WechatOpenConfig($config);
    }

    #[\Override]
    public function name(): Platform
    {
        return Platform::WechatOpen;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new WechatOpenApp($name, $this, $this->wechatOpenConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): WechatOpenConfig
    {
        return $this->wechatOpenConfig;
    }

    /**
     * 桥接到微信主 Provider
     *
     * 当 Kernel 同时配置 wechat 与 wechat_open 时，可通过此方法获取
     * 微信主 Provider，复用其 App、Auth、Pay、Server 等能力。
     */
    public function wechat(): ?WechatProvider
    {
        $kernel = $this->kernel;
        if ($kernel === null) {
            return null;
        }

        $provider = $kernel->wechat();
        return $provider instanceof WechatProvider ? $provider : null;
    }

    /**
     * 开放平台回调统一入口（解密 component_verify_ticket / 授权事件 / 授权方消息）
     *
     * 转发到 {@see WechatOpenApp::notify()}。与支付回调 notify() 互不干扰。
     *
     * @param array<string, mixed> $query 含 msg_signature / timestamp / nonce
     */
    public function handleEvent(string $rawBody, array $query): OpenPlatformEvent
    {
        $app = $this->app();
        if (!$app instanceof WechatOpenApp) {
            throw new \RuntimeException('微信开放平台 app 实例异常');
        }

        return $app->notify($rawBody, $query);
    }
}
