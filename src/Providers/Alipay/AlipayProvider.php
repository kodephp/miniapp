<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseProvider;

/**
 * 支付宝 Provider
 */
final class AlipayProvider extends BaseProvider
{
    /** @var array<string, AlipayApp> */
    private array $apps = [];

    private AlipayConfig $alipayConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new \Kode\MiniApp\Core\HttpClient(), $kernel);
        $this->alipayConfig = new AlipayConfig($config);
    }

    #[\Override]
    public function name(): Platform
    {
        return Platform::Alipay;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new AlipayApp($name, $this, $this->alipayConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): AlipayConfig
    {
        return $this->alipayConfig;
    }
}
