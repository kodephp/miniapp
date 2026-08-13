<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseProvider;

/**
 * 微信 Provider
 */
final class WechatProvider extends BaseProvider
{
    /** @var array<string, WechatApp> */
    private array $apps = [];

    private WechatConfig $wechatConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new \Kode\MiniApp\Core\HttpClient(), $kernel);
        $this->wechatConfig = new WechatConfig($config);
    }

    #[\Override]
    public function name(): Platform
    {
        return Platform::Wechat;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new WechatApp($name, $this, $this->wechatConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): WechatConfig
    {
        return $this->wechatConfig;
    }
}
