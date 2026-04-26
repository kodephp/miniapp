<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Douyin;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\HttpClient;

/**
 * 抖音 Provider
 */
final class DouyinProvider implements PlatformInterface
{
    private DouyinConfig $config;
    private HttpClientInterface $http;

    /** @var array<string, DouyinApp> */
    private array $apps = [];

    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
    ) {
        $this->config = new DouyinConfig($config);
        $this->http   = $http ?? new HttpClient();
    }

    public function name(): Platform
    {
        return Platform::Douyin;
    }

    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new DouyinApp($name, $this, $this->config, $this->http);
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
