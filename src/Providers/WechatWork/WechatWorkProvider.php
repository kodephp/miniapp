<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\HttpClient;

/**
 * 微信企业号 Provider
 */
final class WechatWorkProvider implements PlatformInterface
{
    private WechatWorkConfig $config;
    private HttpClientInterface $http;

    /** @var array<string, WechatWorkApp> */
    private array $apps = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
    ) {
        $this->config = new WechatWorkConfig($config);
        $this->http   = $http ?? new HttpClient();
    }

    public function name(): Platform
    {
        return Platform::WechatWork;
    }

    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new WechatWorkApp($name, $this, $this->config, $this->http);
        }

        return $this->apps[$name];
    }

    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    public function config(): WechatWorkConfig
    {
        return $this->config;
    }
}
