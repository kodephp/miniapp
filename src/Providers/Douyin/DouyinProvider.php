<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Douyin;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseProvider;

/**
 * 抖音 Provider
 */
final class DouyinProvider extends BaseProvider
{
    /** @var array<string, DouyinApp> */
    private array $apps = [];

    private DouyinConfig $douyinConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new \Kode\MiniApp\Core\HttpClient(), $kernel);
        $this->douyinConfig = new DouyinConfig($config);
    }

    public function name(): Platform
    {
        return Platform::Douyin;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new DouyinApp($name, $this, $this->douyinConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): DouyinConfig
    {
        return $this->douyinConfig;
    }
}
