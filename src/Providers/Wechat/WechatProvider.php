<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\HttpClient;
use Kode\MiniApp\Exceptions\ConfigException;

/**
 * 微信 Provider
 */
final class WechatProvider implements PlatformInterface
{
    private WechatConfig $config;
    private HttpClientInterface $http;

    /** @var array<string, WechatApp> */
    private array $apps = [];

    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
    ) {
        $this->config = new WechatConfig($config);
        $this->http   = $http ?? new HttpClient();
    }

    public function name(): Platform
    {
        return Platform::Wechat;
    }

    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new WechatApp($name, $this, $this->config, $this->http);
        }

        return $this->apps[$name];
    }

    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    public function config(): ConfigInterface
    {
        return $this->config;
    }
}
