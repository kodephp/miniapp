<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Lark\Modules\Approval;
use Kode\MiniApp\Providers\Lark\Modules\ApprovalDef;
use Kode\MiniApp\Providers\Lark\Modules\Auth;
use Kode\MiniApp\Providers\Lark\Modules\Bitable;
use Kode\MiniApp\Providers\Lark\Modules\Calendar;
use Kode\MiniApp\Providers\Lark\Modules\Contact;
use Kode\MiniApp\Providers\Lark\Modules\Decrypt;
use Kode\MiniApp\Providers\Lark\Modules\Doc;
use Kode\MiniApp\Providers\Lark\Modules\Mail;
use Kode\MiniApp\Providers\Lark\Modules\Message;
use Kode\MiniApp\Providers\Lark\Modules\Task;
use Kode\MiniApp\Providers\Lark\Modules\Wiki;

/**
 * 飞书应用实例
 */
final readonly class LarkApp implements AppInterface
{
    private Auth $auth;
    private Contact $contact;
    private Message $message;
    private Approval $approval;
    private Bitable $bitable;
    private Doc $doc;
    private Calendar $calendar;
    private Task $task;
    private Wiki $wiki;
    private ApprovalDef $approvalDef;
    private Mail $mail;
    private Decrypt $decrypt;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private LarkConfig $config,
        private HttpClientInterface $http,
    ) {
        $this->auth     = new Auth($this);
        $this->contact  = new Contact($this);
        $this->message  = new Message($this);
        $this->approval = new Approval($this);
        $this->bitable  = new Bitable($this);
        $this->doc      = new Doc($this);
        $this->calendar = new Calendar($this);
        $this->task     = new Task($this);
        $this->wiki     = new Wiki($this);
        $this->approvalDef = new ApprovalDef($this);
        $this->mail     = new Mail($this);
        $this->decrypt  = new Decrypt($this);
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
    public function config(): LarkConfig
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

    public function doc(): Doc
    {
        return $this->doc;
    }

    public function calendar(): Calendar
    {
        return $this->calendar;
    }

    public function task(): Task
    {
        return $this->task;
    }

    public function wiki(): Wiki
    {
        return $this->wiki;
    }

    public function approvalDef(): ApprovalDef
    {
        return $this->approvalDef;
    }

    public function mail(): Mail
    {
        return $this->mail;
    }

    /**
     * 客户端敏感数据解密（encryptedData + session_key）
     *
     * 适用于 tt.getPhoneNumber 返回的加密数据。
     * 飞书 session_key / iv 采用 hex 编码，且解密结果不含 watermark（默认关闭 appid 校验）。
     */
    public function decrypt(): Decrypt
    {
        return $this->decrypt;
    }
}
