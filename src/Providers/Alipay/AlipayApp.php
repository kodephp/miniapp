<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay;

use Kode\MiniApp\Bridge\PayBridge;
use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Notify\Notify;
use Kode\MiniApp\Providers\Alipay\Modules\Auth;
use Kode\MiniApp\Providers\Alipay\Modules\Bill;
use Kode\MiniApp\Providers\Alipay\Modules\Pay;
use Kode\MiniApp\Providers\Alipay\Modules\Transfer;

/**
 * 支付宝应用实例
 * 聚合支付宝所有能力模块
 * 支付能力优先桥接到 kode/pays（如已安装）
 */
final readonly class AlipayApp implements AppInterface
{
    private Auth $auth;
    private Pay $pay;
    private Transfer $transfer;
    private Bill $bill;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private ConfigInterface $config,
        private HttpClientInterface $http,
    ) {
        $this->auth     = new Auth($this);
        $this->pay      = new Pay($this);
        $this->transfer = new Transfer($this);
        $this->bill     = new Bill($this);
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

    public function transfer(): Transfer
    {
        return $this->transfer;
    }

    public function bill(): Bill
    {
        return $this->bill;
    }

    public function notify(): Notify
    {
        return new Notify($this);
    }
}
