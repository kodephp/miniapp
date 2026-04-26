<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Notify\Notify;
use Kode\MiniApp\Providers\WechatWork\Modules\Agent;
use Kode\MiniApp\Providers\WechatWork\Modules\Auth;
use Kode\MiniApp\Providers\WechatWork\Modules\Collect;
use Kode\MiniApp\Providers\WechatWork\Modules\Contact;
use Kode\MiniApp\Providers\WechatWork\Modules\Customer;
use Kode\MiniApp\Providers\WechatWork\Modules\Department;
use Kode\MiniApp\Providers\WechatWork\Modules\Dial;
use Kode\MiniApp\Providers\WechatWork\Modules\ExternalContact;
use Kode\MiniApp\Providers\WechatWork\Modules\Media;
use Kode\MiniApp\Providers\WechatWork\Modules\Meeting;
use Kode\MiniApp\Providers\WechatWork\Modules\Message;
use Kode\MiniApp\Providers\WechatWork\Modules\Oa;
use Kode\MiniApp\Providers\WechatWork\Modules\Approval;
use Kode\MiniApp\Providers\WechatWork\Modules\Schedule;
use Kode\MiniApp\Providers\WechatWork\Modules\Tag;
use Kode\MiniApp\Server\Server;

/**
 * 微信企业号应用实例
 * 聚合企业微信所有能力模块
 */
final readonly class WechatWorkApp implements AppInterface
{
    private Auth $auth;
    private Contact $contact;
    private Message $message;
    private Approval $approval;
    private Customer $customer;
    private Tag $tag;
    private Department $department;
    private ExternalContact $externalContact;
    private Media $media;
    private Agent $agent;
    private Oa $oa;
    private Meeting $meeting;
    private Dial $dial;
    private Schedule $schedule;
    private Collect $collect;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private ConfigInterface $config,
        private HttpClientInterface $http,
    ) {
        $this->auth          = new Auth($this);
        $this->contact       = new Contact($this);
        $this->message       = new Message($this);
        $this->approval      = new Approval($this);
        $this->customer      = new Customer($this);
        $this->tag           = new Tag($this);
        $this->department    = new Department($this);
        $this->externalContact = new ExternalContact($this);
        $this->media         = new Media($this);
        $this->agent         = new Agent($this);
        $this->oa            = new Oa($this);
        $this->meeting       = new Meeting($this);
        $this->dial          = new Dial($this);
        $this->schedule      = new Schedule($this);
        $this->collect       = new Collect($this);
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

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function tag(): Tag
    {
        return $this->tag;
    }

    public function department(): Department
    {
        return $this->department;
    }

    public function externalContact(): ExternalContact
    {
        return $this->externalContact;
    }

    public function media(): Media
    {
        return $this->media;
    }

    public function agent(): Agent
    {
        return $this->agent;
    }

    public function oa(): Oa
    {
        return $this->oa;
    }

    public function meeting(): Meeting
    {
        return $this->meeting;
    }

    public function dial(): Dial
    {
        return $this->dial;
    }

    public function schedule(): Schedule
    {
        return $this->schedule;
    }

    public function collect(): Collect
    {
        return $this->collect;
    }

    public function server(): Server
    {
        return new Server($this);
    }

    public function notify(): Notify
    {
        return new Notify($this);
    }
}
