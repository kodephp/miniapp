<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union;

use InvalidArgumentException;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Baidu\BaiduApp;
use Kode\MiniApp\Providers\Douyin\DouyinApp;
use Kode\MiniApp\Providers\Qq\QqApp;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Session\SessionManager;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\Platforms\AlipayUnion;
use Kode\MiniApp\Union\Platforms\BaiduUnion;
use Kode\MiniApp\Union\Platforms\DingtalkUnion;
use Kode\MiniApp\Union\Platforms\DouyinUnion;
use Kode\MiniApp\Union\Platforms\LarkUnion;
use Kode\MiniApp\Union\Platforms\PlatformUnion;
use Kode\MiniApp\Union\Platforms\QqUnion;
use Kode\MiniApp\Union\Platforms\WechatOpenPlatformUnion;
use Kode\MiniApp\Union\Platforms\WechatUnion;
use Kode\MiniApp\Union\Platforms\WechatWorkUnion;

/**
 * Union 统一入口（门面 + 静态快捷访问）
 *
 * 设计原则：
 *  - 业务侧只需要 `use Kode\MiniApp\Union\Union;` 一行代码
 *  - 通过静态方法 `Union::wechat()` / `Union::alipay()` 等访问各平台
 *  - 通过链式调用 `.mini()` / `.pay()` / `.notify()` 完成所有业务
 *  - 跨端账号通过 UnionID 自动合并
 *
 * 用法：
 *   $user = Union::wechat()->mini('code');                          // 微信小程序登录
 *   $user = Union::wechat()->mp('code');                            // 公众号登录
 *   $order = Union::wechat()->pay()->unifiedOrder([...]);           // 微信支付
 *   $user = Union::alipay()->mini('code');                          // 支付宝小程序登录
 *   $data = Union::wechat()->notify()->decode($payload, $headers);  // 微信回调
 *   $user = Union::wechat()->user('openid')->profile();             // 用户资料
 *
 * 高级用法（从 Kernel 入口）：
 *   $kernel->union()->wechat()->mini('code');
 *
 * @method WechatUnion wechat()
 * @method WechatOpenPlatformUnion wechatOpen()
 * @method WechatOpenPlatformUnion openPlatform()
 * @method AlipayUnion alipay()
 * @method DouyinUnion douyin()
 * @method BaiduUnion baidu()
 * @method QqUnion qq()
 * @method WechatWorkUnion wechatWork()
 * @method WechatWorkUnion work()
 * @method DingtalkUnion dingtalk()
 * @method LarkUnion lark()
 * @method static WechatUnion wechat()
 * @method static WechatOpenPlatformUnion wechatOpen()
 * @method static WechatOpenPlatformUnion openPlatform()
 * @method static AlipayUnion alipay()
 * @method static DouyinUnion douyin()
 * @method static BaiduUnion baidu()
 * @method static QqUnion qq()
 * @method static WechatWorkUnion wechatWork()
 * @method static WechatWorkUnion work()
 * @method static DingtalkUnion dingtalk()
 * @method static LarkUnion lark()
 */
final class Union
{
    /** @var array<string, PlatformUnion> */
    private array $platforms = [];

    /** @var array<string, LoginAdapter> */
    private array $loginAdapters = [];

    /** @var array<string, UserAdapter> */
    private array $userAdapters = [];

    /** @var array<string, PayAdapter> */
    private array $payAdapters = [];

    /** @var array<string, NotifyAdapter> */
    private array $notifyAdapters = [];

    /**
     * 全局 Kernel 引用（供静态方法使用）
     */
    private static ?KernelInterface $globalKernel = null;

    /**
     * 平台方法名 -> (platformKey, unionClass) 映射
     *
     * @var array<string, array{0: string, 1: class-string<PlatformUnion>}>
     */
    private const PLATFORM_MAP = [
        'wechat'       => ['wechat',       WechatUnion::class],
        'wechatOpen'   => ['wechat_open',  WechatOpenPlatformUnion::class],
        'openPlatform' => ['wechat_open',  WechatOpenPlatformUnion::class],
        'alipay'       => ['alipay',       AlipayUnion::class],
        'douyin'       => ['douyin',       DouyinUnion::class],
        'baidu'        => ['baidu',        BaiduUnion::class],
        'qq'           => ['qq',           QqUnion::class],
        'wechatWork'   => ['wechat_work',  WechatWorkUnion::class],
        'work'         => ['wechat_work',  WechatWorkUnion::class],
        'dingtalk'     => ['dingtalk',     DingtalkUnion::class],
        'lark'         => ['lark',         LarkUnion::class],
    ];

    /**
     * 关联的 SessionManager（可选，用于多端登录约束）
     */
    private ?SessionManager $sessionManager = null;

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * 静态初始化（在框架启动时调用一次）
     */
    public static function setKernel(KernelInterface $kernel): void
    {
        self::$globalKernel = $kernel;
    }

    /**
     * 渠道登录认证（底层方法，大多数场景可直接用 Union::xxx()->scene()）
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
        // 将调用方指定的渠道带入适配器 payload。部分适配器（微信 / 抖音 / 支付宝）
        // 内部以 payload['channel'] 判定具体子渠道，若缺省会回退到默认渠道，
        // 导致 Union::profile(Channel::WechatApp, ...) 被误当作公众号 mp 处理。
        $payload['channel'] = $payload['channel'] ?? $channel->value;

        return $this->userAdapter($channel)->profile($openId, $payload);
    }

    /**
     * 解密微信客户端敏感数据（encryptedData + session_key）
     *
     * 业务侧用法（微信小程序 getUserProfile / getPhoneNumber）：
     *   $data = $kernel->union()->decrypt(
     *       Channel::WechatMini,
     *       $encryptedData,   // 前端回传
     *       $sessionKey,      // 登录阶段 jscode2session 拿到的 session_key（敏感，勿下发前端）
     *       $iv,
     *   );
     *
     * 仅微信生态的小程序 / 公众号 / APP 支持；其余渠道抛出异常。
     *
     * @return array<string, mixed>
     */
    public function decrypt(
        Channel $channel,
        string $encryptedData,
        string $sessionKey,
        string $iv,
    ): array {
        [$providerKey, $appClass] = match ($channel) {
            Channel::WechatMini, Channel::WechatMp, Channel::WechatApp => ['wechat', WechatApp::class],
            Channel::DouyinMini, Channel::DouyinMp => ['douyin', DouyinApp::class],
            Channel::BaiduMini => ['baidu', BaiduApp::class],
            Channel::Qq => ['qq', QqApp::class],
            default => throw new InvalidArgumentException(
                "渠道 [{$channel->value}] 暂不支持客户端敏感数据解密",
            ),
        };

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider($providerKey);
        $app      = $provider->app();
        if (!$app instanceof $appClass) {
            throw new \RuntimeException("[{$providerKey}] Provider 实例类型异常，无法解密客户端数据");
        }

        return $app->decrypt()->data($encryptedData, $sessionKey, $iv);
    }

    /**
     * 一站式解密客户端敏感数据（自动取用登录托管的 session_key）
     *
     * 与 {@see self::decrypt()} 的区别：无需手动传 session_key，只要该用户此前
     * 已通过登录接口（code2session）缓存过 session_key，传入其 openid 即可自动取回密钥。
     *
     * 业务侧典型用法：
     *   $user  = Union::wechat()->mini($code);                                  // 登录即托管 session_key
     *   $phone = Union::decryptByUser(Channel::WechatMini, $encryptedData, $iv, $user->openId);
     *
     * @return array<string, mixed>
     */
    public function decryptByUser(Channel $channel, string $encryptedData, string $iv, string $openId): array
    {
        [$providerKey, $appClass] = match ($channel) {
            Channel::WechatMini, Channel::WechatMp, Channel::WechatApp => ['wechat', WechatApp::class],
            Channel::DouyinMini, Channel::DouyinMp => ['douyin', DouyinApp::class],
            Channel::BaiduMini => ['baidu', BaiduApp::class],
            Channel::Qq => ['qq', QqApp::class],
            default => throw new InvalidArgumentException(
                "渠道 [{$channel->value}] 暂不支持客户端敏感数据解密",
            ),
        };

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider($providerKey);
        $app      = $provider->app();
        if (!$app instanceof $appClass) {
            throw new \RuntimeException("[{$providerKey}] Provider 实例类型异常，无法解密客户端数据");
        }

        return $app->decrypt()->dataByUser($encryptedData, $iv, $openId);
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
     * 通用平台访问入口
     *
     * 业务侧可直接通过 `Union::wechat()` / `$kernel->union()->wechat()` 访问。
     * 实际由 `__callStatic` 和 `__call` 转发到本方法。
     *
     * @internal 由 __call / __callStatic 调用
     */
    public function platform(string $key, string $class): PlatformUnion
    {
        if (!isset($this->platforms[$key])) {
            $provider = $this->kernelProvider($key);
            /** @var PlatformUnion $instance */
            $instance = new $class($provider, $this);
            if ($this->sessionManager !== null) {
                $instance->withSession($this->sessionManager);
            }
            $this->platforms[$key] = $instance;
        }
        return $this->platforms[$key];
    }

    /**
     * 设置 SessionManager（用于多端登录约束）
     *
     * 业务侧用法：
     *   $kernel->union()->withSession(new SessionManager(new CacheSessionStorage($redis)));
     *
     * @return $this 支持链式调用
     */
    public function withSession(SessionManager $manager): self
    {
        $this->sessionManager = $manager;
        foreach ($this->platforms as $instance) {
            $instance->withSession($manager);
        }
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
     * 实例级魔术方法：访问平台聚合
     *
     * `$kernel->union()->wechat()` 等价于 `$kernel->union()->platform('wechat', WechatUnion::class)`
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): PlatformUnion
    {
        return $this->resolvePlatform($name, $arguments);
    }

    /**
     * 静态魔术方法：业务侧主入口
     *
     * `Union::wechat()` 等价于 `Union::instance()->wechat()`
     * `Union::wechat()->mini($code)` 一行完成登录
     *
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): PlatformUnion
    {
        return self::instance()->resolvePlatform($name, $arguments);
    }

    /**
     * 获取 Union 单例（基于全局 Kernel）
     */
    public static function instance(): self
    {
        if (self::$globalKernel === null) {
            throw new \RuntimeException(
                'Union 静态方法需要在使用前调用 Union::setKernel($kernel) 初始化，' .
                '或通过 $kernel->union() 入口获取实例。'
            );
        }
        /** @var self $union */
        $union = self::$globalKernel->union();
        return $union;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function resolvePlatform(string $name, array $arguments): PlatformUnion
    {
        if (!isset(self::PLATFORM_MAP[$name])) {
            throw new InvalidArgumentException(
                "Union 不支持平台方法 [{$name}]，可选：" . implode(', ', array_keys(self::PLATFORM_MAP))
            );
        }
        [$key, $class] = self::PLATFORM_MAP[$name];
        if ($arguments !== []) {
            throw new \BadMethodCallException(
                "平台方法 [{$name}] 不接受参数"
            );
        }
        return $this->platform($key, $class);
    }

    // ===== 适配器注册（业务扩展）=====

    /**
     * 注册自定义登录适配器
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

    // ===== 私有：Provider 解析 =====

    private function kernelProvider(string $key): PlatformInterface
    {
        $method = match ($key) {
            'wechat'      => 'wechat',
            'wechat_open' => 'wechatOpen',
            'alipay'      => 'alipay',
            'douyin'      => 'douyin',
            'baidu'       => 'baidu',
            'qq'          => 'qq',
            'wechat_work' => 'wechatWork',
            'dingtalk'    => 'dingtalk',
            'lark'        => 'lark',
            default       => throw new InvalidArgumentException("未知平台标识: {$key}"),
        };

        $provider = $this->kernel->{$method}();
        if (!$provider instanceof PlatformInterface) {
            throw new \RuntimeException("平台 [{$key}] 的 Provider 未注册或类型错误");
        }
        return $provider;
    }

    // ===== 私有：适配器解析 =====

    private function loginAdapter(Channel $channel): LoginAdapter
    {
        $key = $channel->value;
        if (!isset($this->loginAdapters[$key])) {
            $this->loginAdapters[$key] = $this->buildLoginAdapter($channel);
        }
        return $this->loginAdapters[$key];
    }

    private function userAdapter(Channel $channel): UserAdapter
    {
        $key = $channel->value;
        if (!isset($this->userAdapters[$key])) {
            $this->userAdapters[$key] = $this->buildUserAdapter($channel);
        }
        return $this->userAdapters[$key];
    }

    private function payAdapter(Channel $channel): PayAdapter
    {
        $key = $channel->value;
        if (!isset($this->payAdapters[$key])) {
            $this->payAdapters[$key] = $this->buildPayAdapter($channel);
        }
        return $this->payAdapters[$key];
    }

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
            Channel::WechatOpen    => "{$namespace}\\Wechat\\WechatUserAdapter",
            Channel::WechatWork     => "{$namespace}\\WechatWork\\WeWorkUserAdapter",
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
