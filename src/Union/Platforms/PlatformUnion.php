<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Session\Session;
use Kode\MiniApp\Session\SessionManager;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionUser;

/**
 * 平台聚合类（基类）
 *
 * 透明代理对应 Platform / App 的所有能力，
 * 并提供统一的场景登录接口（mini / mp / h5 / pc / app / open / work）。
 *
 * 业务侧只 use 一个 Union 静态门面即可访问所有平台能力。
 */
abstract class PlatformUnion
{
    protected ?SessionManager $sessionManager = null;

    public function __construct(
        protected readonly PlatformInterface $provider,
        protected readonly Union $union,
    ) {
    }

    /**
     * 平台标识（如 wechat / alipay / douyin）
     */
    abstract public function platform(): string;

    /**
     * 关联 SessionManager（用于多端登录约束）
     *
     * @return $this
     */
    public function withSession(SessionManager $manager): static
    {
        $this->sessionManager = $manager;
        return $this;
    }

    /**
     * 获取当前 SessionManager
     */
    public function sessionManager(): ?SessionManager
    {
        return $this->sessionManager;
    }

    /**
     * 平台原生 App 实例（已就绪）
     *
     * @return AppInterface
     */
    public function appInstance(): AppInterface
    {
        return $this->provider->app();
    }

    /**
     * 原始 Provider（如需细粒度访问）
     */
    public function provider(): PlatformInterface
    {
        return $this->provider;
    }

    /**
     * 通用登录入口
     *
     * 通过场景名分发到对应 Channel 适配器。
     * 登录成功后，如果关联了 SessionManager，会自动创建 Session（应用登录约束）。
     *
     * @param array<string, mixed> $payload
     */
    public function login(array $payload, ?string $scene = null): UnionUser
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultChannel();
        $user = $this->union->authenticate($channel, $payload);

        // 可选：自动创建 Session
        $this->autoCreateSession($user, $scene ?? 'default', $payload);

        return $user;
    }

    /**
     * 统一登录（按渠道 + 简单参数）
     */
    public function loginByCode(string $code, ?string $scene = null): UnionUser
    {
        return $this->login(['code' => $code], $scene);
    }

    /**
     * 创建 Session（业务侧显式调用）
     *
     * 用法：
     *   $session = Union::wechat()->createSession($user, 'mini', 'ios', $deviceId);
     *
     * @param array<string, mixed> $payload
     */
    public function createSession(
        UnionUser $user,
        string $scene = 'default',
        string $client = 'web',
        string $clientId = '',
        string $ip = '',
        string $userAgent = '',
        array $payload = [],
    ): ?Session {
        if ($this->sessionManager === null) {
            return null;
        }
        return $this->sessionManager->create(
            $user,
            $scene,
            $client,
            $clientId,
            $ip,
            $userAgent,
            $payload,
        );
    }

    /**
     * 自动创建 Session（内部使用，从 payload 中提取 client/clientId/ip/userAgent）
     *
     * @param array<string, mixed> $payload
     */
    private function autoCreateSession(UnionUser $user, string $scene, array $payload): void
    {
        if ($this->sessionManager === null) {
            return;
        }
        $client   = (string) ($payload['client']    ?? $payload['_client']    ?? '');
        $clientId = (string) ($payload['client_id'] ?? $payload['_client_id'] ?? '');
        $ip       = (string) ($payload['ip']        ?? $payload['_ip']        ?? '');
        $userAgent = (string) ($payload['user_agent'] ?? $payload['_ua']       ?? '');

        $this->sessionManager->create(
            $user,
            $scene,
            $client !== '' ? $client : 'web',
            $clientId,
            $ip,
            $userAgent,
        );
    }

    /**
     * 统一支付适配器
     *
     * ⚠️ 软移交说明：支付能力建议统一交由 kode/pays 承载（下单 / 订单 / 对账 / 退款）。
     * 本包支付适配器为历史保留实现，仅与登录 / 用户共用凭证配置，请勿在新代码中依赖。
     * 详见 {@see \Kode\MiniApp\Union\Contracts\PayAdapter}。
     */
    public function pay(?string $scene = null): PayAdapter
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultPayChannel();
        return $this->union->pay($channel);
    }

    /**
     * 统一下单（直接调用）
     *
     * 便捷写法：把已登录的 {@see UnionUser} 传入，支付适配器会自动注入
     * 平台侧所需的用户标识（如微信 JSAPI 的 openid），确保登录与支付强绑定。
     *
     * 用法：
     *   $user  = Union::wechat()->mini('code');
     *   $order = Union::wechat()->unifiedOrder($params, user: $user);
     *
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function unifiedOrder(array $order, ?string $scene = null, ?UnionUser $user = null): array
    {
        return $this->pay($scene)->unifiedOrder($order, $user);
    }

    /**
     * 统一支付适配器（kode/pays 桥接 / 健壮支付）
     *
     * 委托企业级聚合支付 SDK kode/pays 完成下单。需 `composer require kode/pays`；
     * 未安装时调用会抛清晰异常，可回退到 {@see self::pay()}（基础支付适配器）。
     * 返回结构与 {@see self::pay()} 一致（平台原始数组）。
     *
     * @see \Kode\MiniApp\Union\Bridge\PaysBridge
     */
    public function payViaPays(?string $scene = null): PayAdapter
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultPayChannel();
        return $this->union->payViaPays($channel);
    }

    /**
     * 统一回调适配器
     */
    public function notify(?string $scene = null): NotifyAdapter
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultPayChannel();
        return $this->union->notify($channel);
    }

    /**
     * 统一用户资料
     *
     * @param array<string, mixed> $payload
     */
    public function user(string $openId, array $payload = [], ?string $scene = null): UnionUser
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultChannel();
        return $this->union->profile($channel, $openId, $payload);
    }

    /**
     * 按 unionId 聚合其名下所有渠道的活跃会话（跨端关联展示）
     *
     * 微信生态下，同一用户在不同小程序 / 公众号的 unionId 一致，
     * 可用本方法一次性取出该用户在所有渠道的登录会话，无需自行操作 SessionManager。
     *
     * 未挂载 SessionManager 时返回空数组。
     *
     * @return array<int, \Kode\MiniApp\Session\Session>
     */
    public function sessions(string $unionId): array
    {
        if ($this->sessionManager === null) {
            return [];
        }

        return $this->sessionManager->listByUnionId($unionId);
    }

    /**
     * 通过场景名查找对应 Channel
     */
    protected function channelForScene(string $scene): Channel
    {
        $map = $this->sceneMap();
        $key = strtolower($scene);
        if (!isset($map[$key])) {
            throw new \InvalidArgumentException(
                "平台 [{$this->platform()}] 不支持场景 [{$scene}]，可选：" . implode(', ', array_keys($map))
            );
        }
        return $map[$key];
    }

    /**
     * 子类定义：场景名 -> Channel 映射
     *
     * @return array<string, Channel>
     */
    abstract protected function sceneMap(): array;

    /**
     * 默认登录渠道
     */
    abstract protected function defaultChannel(): Channel;

    /**
     * 默认支付渠道
     */
    abstract protected function defaultPayChannel(): Channel;
}
