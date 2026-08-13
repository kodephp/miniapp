<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseProvider;

/**
 * 微信企业号 Provider
 */
final class WechatWorkProvider extends BaseProvider
{
    /** @var array<string, WechatWorkApp> */
    private array $apps = [];

    private WechatWorkConfig $wechatWorkConfig;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        ?HttpClientInterface $http = null,
        ?KernelInterface $kernel = null,
    ) {
        parent::__construct($config, $http ?? new \Kode\MiniApp\Core\HttpClient(), $kernel);
        $this->wechatWorkConfig = new WechatWorkConfig($config);
    }

    #[\Override]
    public function name(): Platform
    {
        return Platform::WechatWork;
    }

    #[\Override]
    public function app(string $name = 'default'): AppInterface
    {
        if (!isset($this->apps[$name])) {
            $this->apps[$name] = new WechatWorkApp($name, $this, $this->wechatWorkConfig, $this->http);
        }

        return $this->apps[$name];
    }

    #[\Override]
    public function config(): WechatWorkConfig
    {
        return $this->wechatWorkConfig;
    }
}
