<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\HttpClient;

/**
 * 钉钉 Provider
 */
final class DingtalkProvider implements PlatformInterface
{
    private DingtalkConfig $config;
    private HttpClientInterface $http;

    /** @var array<string, DingtalkApp> */
    private array $apps = [];

    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
    ) {
        $this->config = new DingtalkConfig($config);
        $this->http   = $http ?? new HttpClient();
    }

    public function name(): Platform
    {
        return Platform::Dingtalk;
    }

    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new DingtalkApp($name, $this, $this->config, $this->http);
        }

        return $this->apps[$name];
    }

    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    public function config(): DingtalkConfig
    {
        return $this->config;
    }
}
