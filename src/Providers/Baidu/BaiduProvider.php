<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseProvider;

/**
 * 百度 Provider
 */
final class BaiduProvider extends BaseProvider
{
    /** @var array<string, BaiduApp> */
    private array $apps = [];

    private BaiduConfig $baiduConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new \Kode\MiniApp\Core\HttpClient(), $kernel);
        $this->baiduConfig = new BaiduConfig($config);
    }

    public function name(): Platform
    {
        return Platform::Baidu;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new BaiduApp($name, $this, $this->baiduConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): BaiduConfig
    {
        return $this->baiduConfig;
    }
}
