<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union;

use InvalidArgumentException;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Contracts\UserAdapter;

/**
 * 统一入口门面
 *
 * 所有平台（微信 / 支付宝 / 抖音 / 百度 / 钉钉 / 飞书 / 企业微信 / QQ）通过
 * 本类统一对外暴露，业务侧无需关心各平台差异。
 *
 * 用法：
 *   $user = $kernel->union()->authenticate(Channel::WechatMini, ['code' => 'xxx']);
 *   $order = $kernel->union()->pay(Channel::WechatMini)->unifiedOrder([...]);
 *   $data = $kernel->union()->notify(Channel::WechatMini)->decode($payload, $headers);
 *
 * 设计原则：
 *   - 一个渠道 = 一个 Channel
 *   - 平台原始模块（Provider/App/Module）作为内部实现，仍可单独使用
 *   - 统一入口专注于"业务场景"，避免业务侧 use 大量类
 */
final class Union
{
    /** @var array<string, LoginAdapter> */
    private array $loginAdapters = [];

    /** @var array<string, UserAdapter> */
    private array $userAdapters = [];

    /** @var array<string, PayAdapter> */
    private array $payAdapters = [];

    /** @var array<string, NotifyAdapter> */
    private array $notifyAdapters = [];

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * 渠道登录认证（最常用入口）
     *
     * @param array<string, mixed> $payload
     */
    public function authenticate(Channel $channel, array $payload): UnionUser
    {
        return $this->loginAdapter($channel)->authenticate($payload);
    }

    /**
     * 渠道快捷登录（字符串形式）
     *
     * @param array<string, mixed> $payload
     */
    public function login(string $channel, array $payload): UnionUser
    {
        return $this->authenticate(Channel::from($channel), $payload);
    }

    /**
     * 获取用户资料（需 openId）
     *
     * @param array<string, mixed> $payload
     */
    public function profile(Channel $channel, string $openId, array $payload = []): UnionUser
    {
        return $this->userAdapter($channel)->profile($openId, $payload);
    }

    /**
     * 获取支付适配器
     */
    public function pay(Channel $channel): PayAdapter
    {
        return $this->payAdapter($channel);
    }

    /**
     * 获取回调适配器
     */
    public function notify(Channel $channel): NotifyAdapter
    {
        return $this->notifyAdapter($channel);
    }

    /**
     * 注册自定义登录适配器（用于业务侧扩展第三方平台）
     */
    public function registerLoginAdapter(LoginAdapter $adapter): void
    {
        $this->loginAdapters[$adapter->channel()->value] = $adapter;
    }

    /**
     * 注册自定义用户资料适配器
     */
    public function registerUserAdapter(UserAdapter $adapter): void
    {
        $this->userAdapters[$adapter->channel()->value] = $adapter;
    }

    /**
     * 注册自定义支付适配器
     */
    public function registerPayAdapter(PayAdapter $adapter): void
    {
        $this->payAdapters[$adapter->channel()->value] = $adapter;
    }

    /**
     * 注册自定义回调适配器
     */
    public function registerNotifyAdapter(NotifyAdapter $adapter): void
    {
        $this->notifyAdapters[$adapter->channel()->value] = $adapter;
    }

    /**
     * 解析登录适配器
     */
    private function loginAdapter(Channel $channel): LoginAdapter
    {
        $key = $channel->value;
        if (!isset($this->loginAdapters[$key])) {
            $this->loginAdapters[$key] = $this->buildLoginAdapter($channel);
        }

        return $this->loginAdapters[$key];
    }

    /**
     * 解析用户资料适配器
     */
    private function userAdapter(Channel $channel): UserAdapter
    {
        $key = $channel->value;
        if (!isset($this->userAdapters[$key])) {
            $this->userAdapters[$key] = $this->buildUserAdapter($channel);
        }

        return $this->userAdapters[$key];
    }

    /**
     * 解析支付适配器
     */
    private function payAdapter(Channel $channel): PayAdapter
    {
        $key = $channel->value;
        if (!isset($this->payAdapters[$key])) {
            $this->payAdapters[$key] = $this->buildPayAdapter($channel);
        }

        return $this->payAdapters[$key];
    }

    /**
     * 解析回调适配器
     */
    private function notifyAdapter(Channel $channel): NotifyAdapter
    {
        $key = $channel->value;
        if (!isset($this->notifyAdapters[$key])) {
            $this->notifyAdapters[$key] = $this->buildNotifyAdapter($channel);
        }

        return $this->notifyAdapters[$key];
    }

    private function buildLoginAdapter(Channel $channel): LoginAdapter
    {
        $namespace = '\\Kode\\MiniApp\\Union\\Channels';
        $adapter = match ($channel) {
            Channel::WechatMp,
            Channel::WechatH5   => "{$namespace}\\Wechat\\MpLoginAdapter",
            Channel::WechatMini  => "{$namespace}\\Wechat\\MiniLoginAdapter",
            Channel::WechatPc    => "{$namespace}\\WechatOpen\\PcLoginAdapter",
            Channel::WechatApp   => "{$namespace}\\WechatOpen\\AppLoginAdapter",
            Channel::WechatOpen  => "{$namespace}\\WechatOpen\\ComponentLoginAdapter",
            Channel::WechatWork  => "{$namespace}\\WechatWork\\WeWorkLoginAdapter",
            Channel::Qq          => "{$namespace}\\Qq\\QqLoginAdapter",
            Channel::AlipayMini,
            Channel::AlipayMp,
            Channel::AlipayApp   => "{$namespace}\\Alipay\\AlipayLoginAdapter",
            Channel::DouyinMini,
            Channel::DouyinMp    => "{$namespace}\\Douyin\\DouyinLoginAdapter",
            Channel::BaiduMini   => "{$namespace}\\Baidu\\BaiduLoginAdapter",
            Channel::Dingtalk    => "{$namespace}\\Dingtalk\\DingtalkLoginAdapter",
            Channel::Lark        => "{$namespace}\\Lark\\LarkLoginAdapter",
        };

        if (!class_exists($adapter)) {
            throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 的登录适配器尚未实现：{$adapter}"
            );
        }

        return new $adapter($this->kernel);
    }

    private function buildUserAdapter(Channel $channel): UserAdapter
    {
        $namespace = '\\Kode\\MiniApp\\Union\\Channels';
        $adapter = match ($channel) {
            Channel::WechatMp,
            Channel::WechatH5,
            Channel::WechatMini,
            Channel::WechatPc,
            Channel::WechatApp,
            Channel::WechatOpen,
            Channel::WechatWork   => "{$namespace}\\Wechat\\WechatUserAdapter",
            Channel::Qq           => "{$namespace}\\Qq\\QqUserAdapter",
            Channel::AlipayMini,
            Channel::AlipayMp,
            Channel::AlipayApp    => "{$namespace}\\Alipay\\AlipayUserAdapter",
            Channel::DouyinMini,
            Channel::DouyinMp     => "{$namespace}\\Douyin\\DouyinUserAdapter",
            Channel::BaiduMini    => "{$namespace}\\Baidu\\BaiduUserAdapter",
            Channel::Dingtalk     => "{$namespace}\\Dingtalk\\DingtalkUserAdapter",
            Channel::Lark         => "{$namespace}\\Lark\\LarkUserAdapter",
        };

        if (!class_exists($adapter)) {
            throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 的用户资料适配器尚未实现：{$adapter}"
            );
        }

        return new $adapter($this->kernel);
    }

    private function buildPayAdapter(Channel $channel): PayAdapter
    {
        $namespace = '\\Kode\\MiniApp\\Union\\Channels';
        $adapter = match ($channel) {
            Channel::WechatMini,
            Channel::WechatMp     => "{$namespace}\\Wechat\\WechatPayAdapter",
            Channel::WechatApp    => "{$namespace}\\WechatOpen\\AppPayAdapter",
            Channel::WechatWork   => "{$namespace}\\WechatWork\\WeWorkPayAdapter",
            Channel::AlipayMini,
            Channel::AlipayMp,
            Channel::AlipayApp    => "{$namespace}\\Alipay\\AlipayPayAdapter",
            Channel::DouyinMini   => "{$namespace}\\Douyin\\DouyinPayAdapter",
            Channel::BaiduMini    => "{$namespace}\\Baidu\\BaiduPayAdapter",
            default               => throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 不支持支付"
            ),
        };

        if (!class_exists($adapter)) {
            throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 的支付适配器尚未实现：{$adapter}"
            );
        }

        return new $adapter($this->kernel);
    }

    private function buildNotifyAdapter(Channel $channel): NotifyAdapter
    {
        $namespace = '\\Kode\\MiniApp\\Union\\Channels';
        $adapter = match ($channel) {
            Channel::WechatMini,
            Channel::WechatMp,
            Channel::WechatH5,
            Channel::WechatPc,
            Channel::WechatApp,
            Channel::WechatOpen    => "{$namespace}\\Wechat\\WechatNotifyAdapter",
            Channel::WechatWork    => "{$namespace}\\WechatWork\\WeWorkNotifyAdapter",
            Channel::AlipayMini,
            Channel::AlipayMp,
            Channel::AlipayApp     => "{$namespace}\\Alipay\\AlipayNotifyAdapter",
            Channel::DouyinMini,
            Channel::DouyinMp      => "{$namespace}\\Douyin\\DouyinNotifyAdapter",
            Channel::BaiduMini     => "{$namespace}\\Baidu\\BaiduNotifyAdapter",
            default                => throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 不支持回调"
            ),
        };

        if (!class_exists($adapter)) {
            throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 的回调适配器尚未实现：{$adapter}"
            );
        }

        return new $adapter($this->kernel);
    }
}
