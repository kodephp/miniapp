<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\HttpClient;

/**
 * 百度 Provider
 */
final class BaiduProvider implements PlatformInterface
{
    private BaiduConfig $config;
    private HttpClientInterface $http;

    /** @var array<string, BaiduApp> */
    private array $apps = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
    ) {
        $this->config = new BaiduConfig($config);
        $this->http   = $http ?? new HttpClient();
    }

    public function name(): Platform
    {
        return Platform::Baidu;
    }

    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new BaiduApp($name, $this, $this->config, $this->http);
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
