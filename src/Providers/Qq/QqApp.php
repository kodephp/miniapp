<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Qq;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Qq\Modules\Auth;
use Kode\MiniApp\Providers\Qq\Modules\Decrypt;
use Kode\MiniApp\Providers\Qq\Modules\Notify;
use Kode\MiniApp\Providers\Qq\Modules\Pay;

/**
 * QQ 应用实例
 */
final readonly class QqApp implements AppInterface
{
    private Auth $auth;
    private Decrypt $decrypt;
    private Pay $pay;
    private Notify $notify;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private ConfigInterface $config,
        private HttpClientInterface $http,
    ) {
        $this->auth    = new Auth($this);
        $this->decrypt = new Decrypt($this);
        $this->pay     = new Pay($this);
        $this->notify  = new Notify($this);
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

    public function decrypt(): Decrypt
    {
        return $this->decrypt;
    }

    public function pay(): Pay
    {
        return $this->pay;
    }

    /**
     * QQ 支付回调通知处理器（XML 解析 + MD5 验签）
     */
    public function notify(): Notify
    {
        return $this->notify;
    }

    /**
     * 桥接到微信主 Provider
     *
     * QQ 属于微信生态（QQ 与微信用户体系已打通），
     * 业务侧可通过此方法复用微信主 Provider 的能力。
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
}
