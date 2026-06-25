<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\HttpClient;

/**
 * 飞书 Provider
 */
final class LarkProvider implements PlatformInterface
{
    private LarkConfig $config;
    private HttpClientInterface $http;

    /** @var array<string, LarkApp> */
    private array $apps = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
    ) {
        $this->config = new LarkConfig($config);
        $this->http   = $http ?? new HttpClient();
    }

    public function name(): Platform
    {
        return Platform::Lark;
    }

    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new LarkApp($name, $this, $this->config, $this->http);
        }

        return $this->apps[$name];
    }

    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    public function config(): LarkConfig
    {
        return $this->config;
    }
}
