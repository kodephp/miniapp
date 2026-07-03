<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Baidu\Modules\Auth;
use Kode\MiniApp\Providers\Baidu\Modules\Message;
use Kode\MiniApp\Providers\Baidu\Modules\Pay;

/**
 * 百度应用实例
 */
final readonly class BaiduApp implements AppInterface
{
    private Auth $auth;
    private Pay $pay;
    private Message $message;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private ConfigInterface $config,
        private HttpClientInterface $http,
    ) {
        $this->auth = new Auth($this);
        $this->pay  = new Pay($this);
        $this->message = new Message($this);
    }

    #[\Override]
    public function name(): string
    {
        return $this->name;
    }

    #[\Override]
    public function platform(): PlatformInterface
    {
        return $this->platform;
    }

    #[\Override]
    public function config(): ConfigInterface
    {
        return $this->config;
    }

    #[\Override]
    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    public function pay(): Pay
    {
        return $this->pay;
    }

    public function message(): Message
    {
        return $this->message;
    }
}
