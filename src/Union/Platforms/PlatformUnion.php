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
use Kode\MiniApp\Union\Contracts\RefundAdapter;
use Kode\MiniApp\Union\Contracts\CryptoAdapter;
use Kode\MiniApp\Union\Contracts\WebhookAdapter;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Contracts\AdvancedPayAdapter;
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
     * 统一支付适配器（kode/pays 为唯一支付实现）
     *
     * 2.0 起支付能力完全由 kode/pays 承载：本方法返回 pays 桥接适配器，
     * 业务侧调用方式与 kode/pays 网关契约完全一致（createOrder / verifyNotify / ...），详见
     * {@see \Kode\MiniApp\Union\Contracts\PayAdapter}。
     *
     * ⚠️ kode/pays 为硬依赖：未安装时调用会抛清晰异常，引导业务侧先
     * `composer require kode/pays`。
     *
     * @see \Kode\MiniApp\Union\Bridge\PaysBridge
     */
    public function pay(?string $scene = null): PayAdapter
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultPayChannel();
        return $this->union->pay($channel);
    }

    /**
     * 高级支付能力适配器（分账 / 转账 / 对账）
     *
     * 在 {@see self::pay()} 之上，返回实现了 {@see AdvancedPayAdapter} 的实例，
     * 业务侧即可调用 `profitSharingCreate` / `transferSingle` / `reconciliationDownloadBill` 等
     * kode/pays 网关特色方法。适配器未实现该接口时抛清晰异常。
     *
     * 用法：
     *   $res = Union::wechat()->advancedPay()->profitSharingCreate([...]);
     *
     * @see \Kode\MiniApp\Union\Contracts\AdvancedPayAdapter
     */
    public function advancedPay(?string $scene = null): AdvancedPayAdapter
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultPayChannel();
        return $this->union->advancedPay($channel);
    }

    /**
     * 当前渠道「全部高级支付能力」汇总（能力菜单发现，门面级便捷入口）
     *
     * 等价于 {@see self::advancedPay()}()->paymentCapabilities()，但无需持有高级适配器实例，
     * 直接用于「前端按渠道动态渲染能力菜单」或调用前的「一次性自检」：
     *
     *   $caps = Union::wechat()->paymentCapabilities();
     *   // => ['profit_sharing' => true, 'transfer' => true, ...]
     *   if ($caps['red_packet']) {
     *       // 仅微信 V2 支持红包，V3 为 false
     *   }
     *
     * 返回分账 / 转账 / 对账 / 红包 / 订阅 / 余额 / 结算 / 个人收款 / Webhook / 退款
     * 共 10 项能力的布尔开关，**无需完整支付配置**即可调用（基于 kode/pays 网关类能力发现）。
     *
     * @return array<string, bool>
     */
    public function paymentCapabilities(?string $scene = null): array
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultPayChannel();
        return $this->union->paymentCapabilities($channel);
    }

    /**
     * 统一下单（直接调用）
     *
     * 便捷写法：把已登录的 {@see UnionUser} 传入，支付适配器会自动注入
     * 平台侧所需的用户标识（如微信 JSAPI 的 openid），确保登录与支付强绑定。
     *
     * 用法：
     *   $user  = Union::wechat()->mini('code');
     *   $order = Union::wechat()->createOrder($params, user: $user);
     *
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function createOrder(array $order, ?string $scene = null, ?UnionUser $user = null): array
    {
        return $this->pay($scene)->createOrder($order, $user);
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
     * 统一 Webhook 事件回调适配器
     *
     * 与 {@see self::notify()}（同步支付结果通知）不同，面向平台异步推送的「事件型 Webhook」。
     * 返回的 {@see WebhookAdapter} 委托 kode/pays 网关的 WebhookCapableInterface 完成验签 + 解析。
     *
     * 用法：
     *   $wh = Union::wechat()->webhook();
     *   if ($wh->verify($rawBody, $headers)) {
     *       $event = $wh->parse($rawBody);
     *   }
     *
     * @see \Kode\MiniApp\Union\Contracts\WebhookAdapter
     */
    public function webhook(?string $scene = null): WebhookAdapter
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultPayChannel();
        return $this->union->webhook($channel);
    }

    /**
     * 统一退款适配器（申请 / 查询 / 取消退款）
     *
     * 与 {@see self::notify()}（同步支付结果通知）/ {@see self::webhook()}（异步事件）对称，
     * 面向业务侧的「退款闭环」。返回的 {@see RefundAdapter} 委托 kode/pays 网关的
     * RefundCapableInterface 完成 applyRefund / queryRefund / cancelRefund。
     *
     * 用法：
     *   $refund = Union::wechat()->refund();
     *   $res    = $refund->applyRefund(['out_trade_no' => '原支付商户单号', 'out_refund_no' => '商户退款单号', 'amount' => 100]);
     *   $info   = $refund->queryRefund('商户退款单号');
     *
     * @see \Kode\MiniApp\Union\Contracts\RefundAdapter
     */
    public function refund(?string $scene = null): RefundAdapter
    {
        $channel = $scene !== null
            ? $this->channelForScene($scene)
            : $this->defaultPayChannel();
        return $this->union->refund($channel);
    }

    /**
     * 统一加密货币支付适配器（Coinbase 等聚合网关）
     *
     * 与 {@see self::refund()}（法币退款闭环）对称，面向业务侧的「加密货币支付」。
     * 返回的 {@see CryptoAdapter} 委托 kode/pays 网关的 CryptoCapableInterface 完成
     * createCryptoOrder / getPaymentAddresses / getExchangeRate / getConfirmations / 退款 / 异步验签。
     *
     * 加密货币不属于某个既有平台的小程序 / App 场景，故以独立 {@see Channel::Crypto} 表达；
     * 调用方也可显式传入其它渠道（需其网关 implements CryptoCapableInterface）。由于加密货币不在
     * miniapp Kernel 默认渠道凭证体系内，通常通过 `Union::crypto(Channel::Crypto, resolver)` 注入
     * 自定义 config resolver，或 registerCryptoAdapter() 注册适配器。
     *
     * 用法：
     *   $crypto = Union::crypto(Channel::Crypto, fn () => ['api_key' => '...']);
     *   $order  = $crypto->createCryptoOrder(['crypto_currency' => 'BTC', 'fiat_amount' => 100]);
     *
     * @see \Kode\MiniApp\Union\Contracts\CryptoAdapter
     */
    public function crypto(?Channel $channel = null): CryptoAdapter
    {
        return $this->union->crypto($channel ?? Channel::Crypto);
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
