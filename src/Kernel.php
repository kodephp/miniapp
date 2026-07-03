<?php

declare(strict_types=1);

namespace Kode\MiniApp;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\HttpClient;
use Kode\MiniApp\Exceptions\ConfigException;
use Kode\MiniApp\Providers\Alipay\AlipayProvider;
use Kode\MiniApp\Providers\Baidu\BaiduProvider;
use Kode\MiniApp\Providers\Dingtalk\DingtalkProvider;
use Kode\MiniApp\Providers\Douyin\DouyinProvider;
use Kode\MiniApp\Providers\Lark\LarkProvider;
use Kode\MiniApp\Providers\Qq\QqProvider;
use Kode\MiniApp\Providers\Wechat\WechatProvider;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenProvider;
use Kode\MiniApp\Providers\WechatWork\WechatWorkProvider;

/**
 * 门面类，统一入口
 *
 * 用法：
 *   $kernel = new Kernel($config);
 *   $kernel->wechat()->app()->auth->session($code);
 *   $kernel->wechatOpen()->app()->component()->loginPage(...);
 */
final class Kernel implements KernelInterface
{
    /** @var array<string, PlatformInterface> */
    private array $providers = [];

    private readonly HttpClientInterface $http;

    /**
     * @param array<string, array<string, mixed>> $config 全局配置，按平台分组
     */
    public function __construct(
        private readonly array $config,
        ?HttpClientInterface $http = null,
    ) {
        $this->http = $http ?? new HttpClient();
    }

    /**
     * 获取微信 Provider
     */
    public function wechat(): PlatformInterface
    {
        return $this->get(Platform::Wechat);
    }

    /**
     * 获取微信开放平台 Provider
     */
    public function wechatOpen(): PlatformInterface
    {
        return $this->get(Platform::WechatOpen);
    }

    /**
     * 获取支付宝 Provider
     */
    public function alipay(): PlatformInterface
    {
        return $this->get(Platform::Alipay);
    }

    /**
     * 获取抖音 Provider
     */
    public function douyin(): PlatformInterface
    {
        return $this->get(Platform::Douyin);
    }

    /**
     * 获取百度 Provider
     */
    public function baidu(): PlatformInterface
    {
        return $this->get(Platform::Baidu);
    }

    /**
     * 获取 QQ Provider
     */
    public function qq(): PlatformInterface
    {
        return $this->get(Platform::Qq);
    }

    /**
     * 获取微信企业号 Provider
     */
    public function wechatWork(): PlatformInterface
    {
        return $this->get(Platform::WechatWork);
    }

    /**
     * 获取钉钉 Provider
     */
    public function dingtalk(): PlatformInterface
    {
        return $this->get(Platform::Dingtalk);
    }

    /**
     * 获取飞书 Provider
     */
    public function lark(): PlatformInterface
    {
        return $this->get(Platform::Lark);
    }

    /**
     * 通用获取 Provider
     */
    public function get(Platform $platform): PlatformInterface
    {
        $key = $platform->value;

        if (!isset($this->providers[$key])) {
            $this->providers[$key] = $this->resolveProvider($platform);
        }

        return $this->providers[$key];
    }

    /**
     * 创建应用实例（快捷方式）
     */
    public function app(Platform $platform, string $name = 'default'): AppInterface
    {
        return $this->get($platform)->app($name);
    }

    /**
     * 解析并实例化 Provider
     */
    private function resolveProvider(Platform $platform): PlatformInterface
    {
        $config = $this->config[$platform->value] ?? throw new ConfigException(
            "缺少平台 [{$platform->label()}] 的配置"
        );

        return match ($platform) {
            Platform::Wechat     => new WechatProvider($config, $this->http, $this),
            Platform::WechatOpen => new WechatOpenProvider($config, $this->http, $this),
            Platform::Alipay     => new AlipayProvider($config, $this->http, $this),
            Platform::Douyin     => new DouyinProvider($config, $this->http, $this),
            Platform::Baidu      => new BaiduProvider($config, $this->http, $this),
            Platform::Qq         => new QqProvider($config, $this->http, $this),
            Platform::WechatWork => new WechatWorkProvider($config, $this->http, $this),
            Platform::Dingtalk   => new DingtalkProvider($config, $this->http, $this),
            Platform::Lark       => new LarkProvider($config, $this->http, $this),
        };
    }
}
