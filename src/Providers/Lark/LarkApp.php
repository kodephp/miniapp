<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Lark\Modules\Auth;
use Kode\MiniApp\Providers\Lark\Modules\Bitable;
use Kode\MiniApp\Providers\Lark\Modules\Contact;
use Kode\MiniApp\Providers\Lark\Modules\Message;
use Kode\MiniApp\Providers\Lark\Modules\Approval;

/**
 * 飞书应用实例
 */
final readonly class LarkApp implements AppInterface
{
    private Auth     $auth;
    private Contact  $contact;
    private Message  $message;
    private Approval $approval;
    private Bitable  $bitable;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private ConfigInterface $config,
        private HttpClientInterface $http,
    ) {
        $this->auth     = new Auth($this);
        $this->contact  = new Contact($this);
        $this->message  = new Message($this);
        $this->approval = new Approval($this);
        $this->bitable  = new Bitable($this);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function platform(): PlatformInterface
    {
        return $this->platform;
    }

    public function config(): ConfigInterface
    {
        return $this->config;
    }

    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    public function contact(): Contact
    {
        return $this->contact;
    }

    public function message(): Message
    {
        return $this->message;
    }

    public function approval(): Approval
    {
        return $this->approval;
    }

    public function bitable(): Bitable
    {
        return $this->bitable;
    }
}
