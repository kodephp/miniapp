<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseProvider;

/**
 * 飞书 Provider
 */
final class LarkProvider extends BaseProvider
{
    /** @var array<string, LarkApp> */
    private array $apps = [];

    private LarkConfig $larkConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new \Kode\MiniApp\Core\HttpClient(), $kernel);
        $this->larkConfig = new LarkConfig($config);
    }

    public function name(): Platform
    {
        return Platform::Lark;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new LarkApp($name, $this, $this->larkConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): LarkConfig
    {
        return $this->larkConfig;
    }
}
