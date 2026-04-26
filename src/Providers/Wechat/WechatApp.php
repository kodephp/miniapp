<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat;

use Kode\MiniApp\Bridge\PayBridge;
use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Notify\Notify;
use Kode\MiniApp\Providers\Wechat\Modules\Auth;
use Kode\MiniApp\Providers\Wechat\Modules\CustomerService;
use Kode\MiniApp\Providers\Wechat\Modules\DataAnalysis;
use Kode\MiniApp\Providers\Wechat\Modules\Jssdk;
use Kode\MiniApp\Providers\Wechat\Modules\Media;
use Kode\MiniApp\Providers\Wechat\Modules\Menu;
use Kode\MiniApp\Providers\Wechat\Modules\Message;
use Kode\MiniApp\Providers\Wechat\Modules\MiniProgramCode;
use Kode\MiniApp\Providers\Wechat\Modules\Pay;
use Kode\MiniApp\Providers\Wechat\Modules\SubscribeMessage;
use Kode\MiniApp\Providers\Wechat\Modules\User;
use Kode\MiniApp\Server\Server;

/**
 * 微信应用实例
 * 聚合微信所有能力模块
 * 支付能力优先桥接到 kode/pays（如已安装）
 */
final readonly class WechatApp implements AppInterface
{
    private Auth             $auth;
    private Jssdk            $jssdk;
    private Message          $message;
    private Pay              $pay;
    private User             $user;
    private Media            $media;
    private Menu             $menu;
    private CustomerService  $customerService;
    private MiniProgramCode  $miniProgramCode;
    private SubscribeMessage $subscribeMessage;
    private DataAnalysis     $dataAnalysis;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private ConfigInterface $config,
        private HttpClientInterface $http,
    ) {
        $this->auth            = new Auth($this);
        $this->jssdk           = new Jssdk($this);
        $this->message         = new Message($this);
        $this->pay             = new Pay($this);
        $this->user            = new User($this);
        $this->media           = new Media($this);
        $this->menu            = new Menu($this);
        $this->customerService = new CustomerService($this);
        $this->miniProgramCode = new MiniProgramCode($this);
        $this->subscribeMessage = new SubscribeMessage($this);
        $this->dataAnalysis    = new DataAnalysis($this);
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

    public function jssdk(): Jssdk
    {
        return $this->jssdk;
    }

    public function message(): Message
    {
        return $this->message;
    }

    /**
     * 获取支付模块
     * 如安装了 kode/pays，可通过 payBridge() 获取企业级支付能力
     */
    public function pay(): Pay
    {
        return $this->pay;
    }

    /**
     * 获取企业级支付实例（需安装 kode/pays）
     */
    public function payBridge(): ?object
    {
        return PayBridge::getPay($this);
    }

    public function user(): User
    {
        return $this->user;
    }

    public function media(): Media
    {
        return $this->media;
    }

    public function menu(): Menu
    {
        return $this->menu;
    }

    public function customerService(): CustomerService
    {
        return $this->customerService;
    }

    public function miniProgramCode(): MiniProgramCode
    {
        return $this->miniProgramCode;
    }

    public function subscribeMessage(): SubscribeMessage
    {
        return $this->subscribeMessage;
    }

    public function dataAnalysis(): DataAnalysis
    {
        return $this->dataAnalysis;
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
