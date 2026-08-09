<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\ConfigInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Baidu\Modules\Auth;
use Kode\MiniApp\Providers\Baidu\Modules\Decrypt;
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
    private Decrypt $decrypt;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private ConfigInterface $config,
        private HttpClientInterface $http,
    ) {
        $this->auth = new Auth($this);
        $this->pay  = new Pay($this);
        $this->message = new Message($this);
        $this->decrypt = new Decrypt($this);
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

    /**
     * 客户端敏感数据解密（encryptedData + session_key）
     *
     * 适用于 swan.getPhoneNumber / getUserInfo 返回的加密数据。
     * 内部自动校验 watermark.appid 与当前小程序 appId 一致。
     */
    public function decrypt(): Decrypt
    {
        return $this->decrypt;
    }
}
