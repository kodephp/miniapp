<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Dingtalk\Modules\Attendance;
use Kode\MiniApp\Providers\Dingtalk\Modules\Auth;
use Kode\MiniApp\Providers\Dingtalk\Modules\Contact;
use Kode\MiniApp\Providers\Dingtalk\Modules\Hrm;
use Kode\MiniApp\Providers\Dingtalk\Modules\Message;
use Kode\MiniApp\Providers\Dingtalk\Modules\Approval;
use Kode\MiniApp\Providers\Dingtalk\Modules\Project;
use Kode\MiniApp\Providers\Dingtalk\Modules\Report;
use Kode\MiniApp\Providers\Dingtalk\Modules\Robot;
use Kode\MiniApp\Providers\Dingtalk\Modules\Workflow;

/**
 * 钉钉应用实例
 */
final readonly class DingtalkApp implements AppInterface
{
    private Auth $auth;
    private Contact $contact;
    private Message $message;
    private Approval $approval;
    private Robot $robot;
    private Attendance $attendance;
    private Hrm $hrm;
    private Report $report;
    private Project $project;
    private Workflow $workflow;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private DingtalkConfig $config,
        private HttpClientInterface $http,
    ) {
        $this->auth     = new Auth($this);
        $this->contact  = new Contact($this);
        $this->message  = new Message($this);
        $this->approval = new Approval($this);
        $this->robot    = new Robot($this);
        $this->attendance = new Attendance($this);
        $this->hrm      = new Hrm($this);
        $this->report   = new Report($this);
        $this->project  = new Project($this);
        $this->workflow = new Workflow($this);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function platform(): PlatformInterface
    {
        return $this->platform;
    }

    public function config(): DingtalkConfig
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

    public function robot(): Robot
    {
        return $this->robot;
    }

    public function attendance(): Attendance
    {
        return $this->attendance;
    }

    public function hrm(): Hrm
    {
        return $this->hrm;
    }

    public function report(): Report
    {
        return $this->report;
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function workflow(): Workflow
    {
        return $this->workflow;
    }
}
