<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\WechatOpen\Modules\Authorizer;
use Kode\MiniApp\Providers\WechatOpen\Modules\Component;
use Kode\MiniApp\Providers\WechatOpen\Modules\Crypto;
use Kode\MiniApp\Providers\WechatOpen\Modules\OpenApp;
use Kode\MiniApp\Providers\WechatOpen\Modules\UnionId;

/**
 * 微信开放平台应用实例
 *
 * 聚合微信开放平台能力：
 *  - component：第三方平台 component_access_token、pre_auth_code、授权页 URL
 *  - authorizer：代公众号 / 小程序调用接口、管理授权方
 *  - openApp：移动应用、网站应用 UnionID 登录
 *  - crypto：消息加解密、签名校验
 *  - unionId：UnionID 机制工具方法
 */
final readonly class WechatOpenApp implements AppInterface
{
    private Component $component;
    private Authorizer $authorizer;
    private OpenApp $openApp;
    private Crypto $crypto;
    private UnionId $unionId;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private WechatOpenConfig $config,
        private HttpClientInterface $http,
    ) {
        $this->component  = new Component($this);
        $this->authorizer = new Authorizer($this);
        $this->openApp    = new OpenApp($this);
        $this->crypto     = new Crypto($this);
        $this->unionId    = new UnionId($this);
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
    public function config(): WechatOpenConfig
    {
        return $this->config;
    }

    #[\Override]
    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    /**
     * 第三方平台自身能力（component_access_token、pre_auth_code 等）
     */
    public function component(): Component
    {
        return $this->component;
    }

    /**
     * 授权方管理：代公众号 / 小程序调用接口、查询授权信息
     */
    public function authorizer(): Authorizer
    {
        return $this->authorizer;
    }

    /**
     * 移动应用 / 网站应用：UnionID 登录、用户信息
     */
    public function openApp(): OpenApp
    {
        return $this->openApp;
    }

    /**
     * 消息加解密（处理微信开放平台回调、component_verify_ticket）
     */
    public function crypto(): Crypto
    {
        return $this->crypto;
    }

    /**
     * UnionID 相关辅助
     */
    public function unionId(): UnionId
    {
        return $this->unionId;
    }

    /**
     * 桥接到微信主 Provider
     *
     * 适用于：开放平台代公众号 / 小程序调用接口时，复用微信主 Provider 的能力。
     * 当 Kernel 中同时配置 wechat 与 wechat_open 时，可通过此方法获取微信主 Provider。
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
