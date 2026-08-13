<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Qq;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseProvider;

/**
 * QQ Provider
 */
final class QqProvider extends BaseProvider
{
    /** @var array<string, QqApp> */
    private array $apps = [];

    private QqConfig $qqConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new \Kode\MiniApp\Core\HttpClient(), $kernel);
        $this->qqConfig = new QqConfig($config);
    }

    #[\Override]
    public function name(): Platform
    {
        return Platform::Qq;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new QqApp($name, $this, $this->qqConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): QqConfig
    {
        return $this->qqConfig;
    }
}
