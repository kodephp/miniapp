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
use Kode\MiniApp\Providers\WechatWork\Modules\CorpGroup;
use Kode\MiniApp\Providers\WechatWork\Modules\Decrypt;
use Kode\MiniApp\Providers\WechatWork\Modules\Customer;
use Kode\MiniApp\Providers\WechatWork\Modules\Department;
use Kode\MiniApp\Providers\WechatWork\Modules\Dial;
use Kode\MiniApp\Providers\WechatWork\Modules\Drive;
use Kode\MiniApp\Providers\WechatWork\Modules\ExternalContact;
use Kode\MiniApp\Providers\WechatWork\Modules\Media;
use Kode\MiniApp\Providers\WechatWork\Modules\Meeting;
use Kode\MiniApp\Providers\WechatWork\Modules\Message;
use Kode\MiniApp\Providers\WechatWork\Modules\Msghub;
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
    private Decrypt $decrypt;
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
    private Drive $drive;
    private CorpGroup $corpGroup;
    private Msghub $msghub;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private WechatWorkConfig $config,
        private HttpClientInterface $http,
    ) {
        $this->auth          = new Auth($this);
        $this->contact       = new Contact($this);
        $this->decrypt       = new Decrypt($this);
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
        $this->drive         = new Drive($this);
        $this->corpGroup     = new CorpGroup($this);
        $this->msghub        = new Msghub($this);
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
    public function config(): WechatWorkConfig
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

    /**
     * 客户端敏感数据解密（encryptedData + session_key）
     */
    public function decrypt(): Decrypt
    {
        return $this->decrypt;
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

    public function drive(): Drive
    {
        return $this->drive;
    }

    public function corpGroup(): CorpGroup
    {
        return $this->corpGroup;
    }

    public function msghub(): Msghub
    {
        return $this->msghub;
    }

    public function server(): Server
    {
        return new Server($this);
    }

    public function notify(): Notify
    {
        return new Notify($this);
    }

    /**
     * 桥接到微信主 Provider
     *
     * 适用于：企业微信关联公众号 / 小程序场景的协同处理。
     * 当 Kernel 中同时配置 wechat 与 wechat_work 时，可通过此方法获取微信主 Provider。
     */
    public function wechat(): ?\Kode\MiniApp\Providers\Wechat\WechatProvider
    {
        $kernel = $this->platform->kernel();
        if ($kernel === null) {
            return null;
        }

        $provider = $kernel->wechat();
        return $provider instanceof \Kode\MiniApp\Providers\Wechat\WechatProvider
            ? $provider
            : null;
    }

    /**
     * 桥接到微信开放平台 Provider
     */
    public function wechatOpen(): ?\Kode\MiniApp\Providers\WechatOpen\WechatOpenProvider
    {
        $kernel = $this->platform->kernel();
        if ($kernel === null) {
            return null;
        }

        $provider = $kernel->wechatOpen();
        return $provider instanceof \Kode\MiniApp\Providers\WechatOpen\WechatOpenProvider
            ? $provider
            : null;
    }
}
