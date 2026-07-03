<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseProvider;

/**
 * 钉钉 Provider
 */
final class DingtalkProvider extends BaseProvider
{
    /** @var array<string, DingtalkApp> */
    private array $apps = [];

    private DingtalkConfig $dingtalkConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new \Kode\MiniApp\Core\HttpClient(), $kernel);
        $this->dingtalkConfig = new DingtalkConfig($config);
    }

    public function name(): Platform
    {
        return Platform::Dingtalk;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new DingtalkApp($name, $this, $this->dingtalkConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): DingtalkConfig
    {
        return $this->dingtalkConfig;
    }
}
