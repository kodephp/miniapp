<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union;

use InvalidArgumentException;
use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Core\PhoneNormalizer;
use Kode\MiniApp\Core\UserInfoNormalizer;
use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Providers\Baidu\BaiduApp;
use Kode\MiniApp\Providers\Douyin\DouyinApp;
use Kode\MiniApp\Providers\Lark\LarkApp;
use Kode\MiniApp\Providers\Qq\QqApp;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;
use Kode\MiniApp\Session\SessionManager;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionPhone;
use Kode\MiniApp\Union\UnionUser;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;
use Kode\MiniApp\Union\Contracts\WebhookAdapter;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Contracts\AdvancedPayAdapter;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Contracts\RefundAdapter;
use Kode\MiniApp\Union\Contracts\CryptoAdapter;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\CapabilityInfo;
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
 *   $order = Union::wechat()->pay()->createOrder([...]);           // 微信支付
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

    /** @var array<string, WebhookAdapter> */
    private array $webhookAdapters = [];

    /** @var array<string, RefundAdapter> */
    private array $refundAdapters = [];

    /** @var array<string, CryptoAdapter> */
    private array $cryptoAdapters = [];

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
        [$providerKey, $appClass] = $this->decryptChannel($channel);

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
        [$providerKey, $appClass] = $this->decryptChannel($channel);

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider($providerKey);
        $app      = $provider->app();
        if (!$app instanceof $appClass) {
            throw new \RuntimeException("[{$providerKey}] Provider 实例类型异常，无法解密客户端数据");
        }

        return $app->decrypt()->dataByUser($encryptedData, $iv, $openId);
    }

    /**
     * 手机号快速验证：用前端动态令牌 code 换取手机号（新版方式，无需 session_key）
     *
     * 与 {@see decrypt()} / {@see decryptByUser()}（旧版 encryptedData + session_key 解密）
     * 互为两条并行路径。微信自基础库 2.21.2 起，`<button open-type="getPhoneNumber">`
     * 回调返回的是动态令牌 code，官方建议改用本方式。
     *
     * 业务侧用法：
     *   $info = $kernel->union()->phoneByCode(Channel::WechatMini, $code);
     *   $info['phoneNumber'];      // 带区号
     *   $info['purePhoneNumber'];  // 不带区号
     *
     * 覆盖范围：微信小程序（返回明文 phone_info）、抖音小程序（返回 RSA 密文，
     * 由 SDK 用配置的 `app_private_key` 自动解密，业务侧拿到的同样是明文数组）。
     * 百度 / QQ 仍只提供 encryptedData 解密，不在本方法覆盖范围内。
     *
     * @param string      $code   前端 bindgetphonenumber 回调中的动态令牌（5 分钟内有效、仅可消费一次）
     * @param string|null $openId 可选，用户 openid（仅微信接受该选填参数，抖音接口不需要）
     *
     * @throws InvalidArgumentException 渠道不支持 code 换手机号
     * @return array<string, mixed>
     */
    public function phoneByCode(Channel $channel, string $code, ?string $openId = null): array
    {
        [$providerKey, $appClass, $acceptsOpenId] = match ($channel) {
            Channel::WechatMini => ['wechat', WechatApp::class, true],
            Channel::DouyinMini => ['douyin', DouyinApp::class, false],
            default => throw new InvalidArgumentException(
                "渠道 [{$channel->value}] 暂不支持 code 换取手机号",
            ),
        };

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider($providerKey);
        $app      = $provider->app();
        if (!$app instanceof $appClass) {
            throw new \RuntimeException("[{$providerKey}] Provider 实例类型异常，无法换取手机号");
        }

        $info = $acceptsOpenId
            ? $app->phone()->byCode($code, $openId)
            : $app->phone()->byCode($code);

        // 归一化：确保返回结构含统一的 phoneNumber / purePhoneNumber / countryCode
        // （微信 / 抖音本就以此命名，此处仅做兜底与未来平台兼容；原字段全部保留）。
        /** @var array<string, mixed> $info */
        return array_merge($info, PhoneNormalizer::normalize($info));
    }

    /**
     * 手机号输出归一化（统一工具）
     *
     * 将任意端「手机号获取」的原始数组（微信 / 抖音的 phoneNumber，或支付宝的 mobile 等）
     * 归一化为一致的三元组 `phoneNumber` / `purePhoneNumber` / `countryCode`，便于业务侧
     * 以统一结构消费。纯函数，输入缺字段时对应值为空字符串，绝不抛异常。
     *
     * @param array<string, mixed> $raw
     *
     * @return array{phoneNumber:string, purePhoneNumber:string, countryCode:string}
     */
    public static function normalizePhone(array $raw): array
    {
        return PhoneNormalizer::normalize($raw);
    }

    /**
     * 用户资料输出归一化（统一工具）
     *
     * 将各端 encryptedData 解密出的用户资料原始数组（兼容微信 getUserInfo 的
     * `nickName` / `avatarUrl` / `gender` / `city` / `province` / `country` / `language`）
     * 归一化为稳定的 snake_case canonical 键（nickname / avatar / gender / city / province /
     * country / language），与 {@see \Kode\MiniApp\Union\UnionUser}（登录 / profile 链路）字段命名对齐。
     * 纯函数，输入缺字符串字段时对应值为空字符串、gender 缺失为 null，绝不抛异常。
     *
     * @param array<string, mixed> $raw
     *
     * @return array{
     *     nickname:string, avatar:string, gender:mixed,
     *     city:string, province:string, country:string, language:string
     * }
     */
    public static function normalizeUserInfo(array $raw): array
    {
        return UserInfoNormalizer::normalize($raw);
    }

    /**
     * 统一「encryptedData 解密获取手机号」入口（显式 session_key）
     *
     * 与 {@see phoneByCode()}（新版 code 换手机号，仅微信 / 抖音）互为两条并行路径，
     * 本方法对应旧版 encryptedData + session_key 解密路径，覆盖微信 / 抖音 / QQ / 百度 /
     * 飞书 / 企业微信 六个端（支付宝走 {@see phoneByResponse()}，因其为 response + sign
     * 而非 encryptedData，不在本方法范围内）。
     *
     * 返回经 {@see PhoneNormalizer} 归一化的统一结构（原字段全部保留）。
     *
     * @param string $encryptedData 客户端回传的加密数据
     * @param string $sessionKey    会话密钥（与加密时一致）
     * @param string $iv            加密初始向量
     *
     * @throws InvalidArgumentException 渠道不支持 encryptedData 解密获取手机号
     * @return array<string, mixed>
     */
    public function phoneByDecrypt(
        Channel $channel,
        string $encryptedData,
        string $sessionKey,
        string $iv,
    ): array {
        [$providerKey, $appClass] = $this->decryptChannel($channel);

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider($providerKey);
        $app      = $provider->app();
        if (!$app instanceof $appClass) {
            throw new \RuntimeException("[{$providerKey}] Provider 实例类型异常，无法解密手机号");
        }

        /** @var array<string, mixed> $info */
        $info = $app->decrypt()->phone($encryptedData, $sessionKey, $iv);

        return array_merge($info, PhoneNormalizer::normalize($info));
    }

    /**
     * 统一「encryptedData 解密获取手机号」入口（自动取用登录托管的 session_key）
     *
     * 与 {@see decryptByUser()} 的区别：本方法直接返回归一化后的手机号数组，
     * 无需业务侧再手动取字段。只要该用户此前已通过登录接口（code2session）缓存过
     * session_key，传入其 openid 即可自动取回密钥完成解密。
     *
     * @param string $encryptedData 客户端回传的加密数据
     * @param string $iv            加密初始向量
     * @param string $openId        用户 openid（用于取回托管的 session_key）
     *
     * @throws InvalidArgumentException 渠道不支持 encryptedData 解密获取手机号
     * @return array<string, mixed>
     */
    public function phoneByUser(Channel $channel, string $encryptedData, string $iv, string $openId): array
    {
        [$providerKey, $appClass] = $this->decryptChannel($channel);

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider($providerKey);
        $app      = $provider->app();
        if (!$app instanceof $appClass) {
            throw new \RuntimeException("[{$providerKey}] Provider 实例类型异常，无法解密手机号");
        }

        /** @var array<string, mixed> $info */
        $info = $app->decrypt()->phoneByUser($encryptedData, $iv, $openId);

        return array_merge($info, PhoneNormalizer::normalize($info));
    }

    /**
     * 统一「支付宝 response + sign 换取手机号」入口（打破原设计 fence）
     *
     * 支付宝小程序 `my.getPhoneNumber` 前端回传的是加密 `response`（AES-128-CBC，全零 IV，
     * key = base64_decode(aes_key)）与 RSA2 `sign`，既无 code 也无 encryptedData / session_key，
     * 因此其手机号获取与微信 / 抖音（code）及 QQ / 百度 / 飞书 / 企业微信（encryptedData）
     * 的输入形态都不同，此前只能走 `Union::alipay()->decrypt()->phone()` 这一底层入口。
     *
     * 本方法把支付宝也纳入 `Union` 的统一手机号家族，使三族入口形态一致：
     *   - 微信 / 抖音：`phoneByCode($code, $openId)`
     *   - QQ / 百度 / 飞书 / 企业微信：`phoneByDecrypt()` / `phoneByUser()`
     *   - 支付宝：`phoneByResponse($response, $sign)`
     *
     * 传入 `sign` 时会先做 RSA2 验签（防中间人篡改），验签失败直接抛 `ApiException`；
     * 不传 `sign` 则跳过验签（仍完成解密，仅失去篡改防护，不推荐生产环境）。
     * 返回数组经支付宝侧归一化，含 `mobile` / `countryCode` 及统一的
     * `phoneNumber` / `purePhoneNumber` / `countryCode`。
     *
     * @param string      $response 前端回传的加密 response（base64）
     * @param string|null $sign     可选，前端回传的 RSA2 签名（强烈建议传入）
     *
     * @throws InvalidArgumentException 渠道不是支付宝（仅支付宝使用 response + sign 方式）
     * @return array<string, mixed>
     */
    public function phoneByResponse(Channel $channel, string $response, ?string $sign = null): array
    {
        if (
            $channel !== Channel::AlipayMini
            && $channel !== Channel::AlipayMp
            && $channel !== Channel::AlipayApp
        ) {
            throw new InvalidArgumentException(
                "渠道 [{$channel->value}] 暂不支持 response + sign 换取手机号（仅支付宝使用此方式）",
            );
        }

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider('alipay');
        $app      = $provider->app();
        if (!$app instanceof AlipayApp) {
            throw new \RuntimeException('[alipay] Provider 实例类型异常，无法解密手机号');
        }

        return $app->decrypt()->phone($response, $sign);
    }

    /**
     * 手机号快速验证：code 换手机号，并直接收敛为 UnionPhone 对象
     *
     * 与 {@see phoneByCode()}（返回数组）的唯一区别：在换取 + 归一化之后，进一步把结果
     * 收敛为与 {@see UnionPhone} 强类型值对象，业务侧无需再手写数组取值。
     * 分派逻辑、覆盖范围、不支持渠道抛错行为均与 {@see phoneByCode()} 一致（底层复用之）。
     *
     * @param string      $code   前端动态令牌（5 分钟内有效、仅可消费一次）
     * @param string|null $openId 可选，用户 openid（仅微信接受该选填参数，抖音接口不需要）
     *
     * @throws InvalidArgumentException 渠道不支持 code 换手机号
     */
    public function phoneObjectByCode(Channel $channel, string $code, ?string $openId = null): UnionPhone
    {
        return UnionPhone::fromArray($this->phoneByCode($channel, $code, $openId));
    }

    /**
     * 统一「encryptedData 解密获取手机号」入口，并直接收敛为 UnionPhone 对象
     *
     * 与 {@see phoneByDecrypt()}（返回数组）的唯一区别：收敛为 {@see UnionPhone} 值对象。
     * 分派逻辑、覆盖范围、不支持渠道抛错行为均与 {@see phoneByDecrypt()} 一致（底层复用之）。
     *
     * @param string $encryptedData 客户端回传的加密数据
     * @param string $sessionKey    会话密钥（与加密时一致）
     * @param string $iv            加密初始向量
     *
     * @throws InvalidArgumentException 渠道不支持 encryptedData 解密获取手机号
     */
    public function phoneObjectByDecrypt(
        Channel $channel,
        string $encryptedData,
        string $sessionKey,
        string $iv,
    ): UnionPhone {
        return UnionPhone::fromArray($this->phoneByDecrypt($channel, $encryptedData, $sessionKey, $iv));
    }

    /**
     * 统一「encryptedData 解密获取手机号」入口（自动取用登录托管的 session_key），收敛为 UnionPhone
     *
     * 与 {@see phoneObjectByDecrypt()} 的区别：无需手动传 session_key，只要该用户此前已通过
     * 登录接口（code2session）缓存过 session_key，传入其 openid 即可自动取回密钥完成解密，
     * 随后收敛为 {@see UnionPhone} 值对象。其余行为同 {@see phoneObjectByDecrypt()}。
     *
     * @param string $encryptedData 客户端回传的加密数据
     * @param string $iv            加密初始向量
     * @param string $openId        用户 openid（用于取回托管的 session_key）
     *
     * @throws InvalidArgumentException 渠道不支持 encryptedData 解密获取手机号
     */
    public function phoneObjectByUser(Channel $channel, string $encryptedData, string $iv, string $openId): UnionPhone
    {
        return UnionPhone::fromArray($this->phoneByUser($channel, $encryptedData, $iv, $openId));
    }

    /**
     * 统一「支付宝 response + sign 换取手机号」入口，并直接收敛为 UnionPhone 对象
     *
     * 与 {@see phoneByResponse()}（返回数组）的唯一区别：收敛为 {@see UnionPhone} 值对象。
     * 验签（传入 sign 时）/ 解密 / 覆盖范围 / 抛错行为均与 {@see phoneByResponse()} 一致（底层复用之）。
     *
     * @param string      $response 前端回传的加密 response（base64）
     * @param string|null $sign     可选，前端回传的 RSA2 签名（强烈建议传入）
     *
     * @throws InvalidArgumentException 渠道不是支付宝（仅支付宝使用 response + sign 方式）
     */
    public function phoneObjectByResponse(Channel $channel, string $response, ?string $sign = null): UnionPhone
    {
        return UnionPhone::fromArray($this->phoneByResponse($channel, $response, $sign));
    }

    /**
     * 统一「encryptedData 解密获取用户资料」入口（显式 session_key）
     *
     * 与 {@see phoneByDecrypt()}（手机号）、{@see decrypt()}（通用 data）互为同族，
     * 覆盖微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 六个端；支付宝走 `Union::alipay()->decrypt()->data()`
     * （response + sign，无 encryptedData），不在本方法范围内。
     *
     * 返回各端用户资料数组：保留原始字段，并追加经 {@see UserInfoNormalizer} 归一化的
     * snake_case canonical 键（nickname / avatar / gender / city / province / country / language），
     * 与 UnionUser（登录 / profile 链路）字段命名对齐。
     *
     * @param string $encryptedData 客户端回传的加密数据
     * @param string $sessionKey    会话密钥（与加密时一致）
     * @param string $iv            加密初始向量
     *
     * @throws InvalidArgumentException 渠道不支持 encryptedData 解密获取用户资料
     * @return array<string, mixed>
     */
    public function userInfoByDecrypt(
        Channel $channel,
        string $encryptedData,
        string $sessionKey,
        string $iv,
    ): array {
        [$providerKey, $appClass] = $this->decryptChannel($channel);

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider($providerKey);
        $app      = $provider->app();
        if (!$app instanceof $appClass) {
            throw new \RuntimeException("[{$providerKey}] Provider 实例类型异常，无法解密用户资料");
        }

        $info = $app->decrypt()->userInfo($encryptedData, $sessionKey, $iv);

        // 归一化：在保留原始字段的同时追加统一的 snake_case canonical 键
        // （nickname / avatar / gender / city / province / country / language），
        // 与 UnionUser（登录 / profile 链路）字段命名对齐，便于业务侧统一消费。
        return array_merge($info, UserInfoNormalizer::normalize($info));
    }

    /**
     * 统一「encryptedData 解密获取用户资料」入口（自动取用登录托管的 session_key）
     *
     * 与 {@see userInfoByDecrypt()} 的区别：无需手动传 session_key，只要该用户此前已通过
     * 登录接口（code2session）缓存过 session_key，传入其 openid 即可自动取回密钥完成解密。
     *
     * @param string $encryptedData 客户端回传的加密数据
     * @param string $iv            加密初始向量
     * @param string $openId        用户 openid（用于取回托管的 session_key）
     *
     * @throws InvalidArgumentException 渠道不支持 encryptedData 解密获取用户资料
     * @return array<string, mixed>
     */
    public function userInfoByUser(Channel $channel, string $encryptedData, string $iv, string $openId): array
    {
        [$providerKey, $appClass] = $this->decryptChannel($channel);

        /** @var PlatformInterface $provider */
        $provider = $this->kernelProvider($providerKey);
        $app      = $provider->app();
        if (!$app instanceof $appClass) {
            throw new \RuntimeException("[{$providerKey}] Provider 实例类型异常，无法解密用户资料");
        }

        $info = $app->decrypt()->userInfoByUser($encryptedData, $iv, $openId);

        return array_merge($info, UserInfoNormalizer::normalize($info));
    }

    /**
     * 统一「encryptedData 解密获取用户资料」入口，并直接收敛为 UnionUser 对象
     *
     * 与 {@see userInfoByDecrypt()}（返回数组）的唯一区别：在解密 + 归一化之后，
     * 进一步把结果收敛为与登录 / profile 链路完全相同的 {@see UnionUser} 对象，
     * 业务侧无需再手写字段映射即可统一消费。分派逻辑、覆盖范围、不支持渠道抛错
     * 行为均与 {@see userInfoByDecrypt()} 一致（底层复用之）。
     *
     * openId / unionId 在加密用户资料明文里并不存在（来自登录 code2session / 开放平台），
     * 故由调用方按需传入，缺失时留空。gender 仅透传、不做枚举映射。
     *
     * @param string      $encryptedData 客户端回传的加密数据
     * @param string      $sessionKey    会话密钥（与加密时一致）
     * @param string      $iv            加密初始向量
     * @param string|null $openId        可选，用户 openid（来自登录）
     * @param string|null $unionId       可选，跨平台 unionId（来自开放平台）
     *
     * @throws InvalidArgumentException 渠道不支持 encryptedData 解密获取用户资料
     */
    public function userInfoObjectByDecrypt(
        Channel $channel,
        string $encryptedData,
        string $sessionKey,
        string $iv,
        ?string $openId = null,
        ?string $unionId = null,
    ): UnionUser {
        $info = $this->userInfoByDecrypt($channel, $encryptedData, $sessionKey, $iv);

        return UnionUser::fromDecryptedUserInfo($channel, $info, $openId, $unionId);
    }

    /**
     * 统一「encryptedData 解密获取用户资料」入口（自动取用登录托管的 session_key），收敛为 UnionUser
     *
     * 与 {@see userInfoObjectByDecrypt()} 的区别：无需手动传 session_key，只要该用户此前已通过
     * 登录接口（code2session）缓存过 session_key，传入其 openid 即可自动取回密钥完成解密，
     * 随后收敛为 {@see UnionUser} 对象。其余行为同 {@see userInfoObjectByDecrypt()}。
     *
     * @param string      $encryptedData 客户端回传的加密数据
     * @param string      $iv            加密初始向量
     * @param string      $openId        用户 openid（用于取回托管的 session_key）
     * @param string|null $unionId       可选，跨平台 unionId（来自开放平台）
     *
     * @throws InvalidArgumentException 渠道不支持 encryptedData 解密获取用户资料
     */
    public function userInfoObjectByUser(
        Channel $channel,
        string $encryptedData,
        string $iv,
        string $openId,
        ?string $unionId = null,
    ): UnionUser {
        $info = $this->userInfoByUser($channel, $encryptedData, $iv, $openId);

        return UnionUser::fromDecryptedUserInfo($channel, $info, $openId, $unionId);
    }

    /**
     * 从已登录的 UnionUser 一键解密手机号（桥接入口，免重复传参）
     *
     * 登录后业务侧已拿到 {@see UnionUser}（含 channel 与 openId），且 `code2session` 阶段
     * 已自动把 session_key 按 openId 托管到 {@see \Kode\MiniApp\Core\SessionKeyManager}。
     * 本方法直接从该对象取回 channel / openId，复用 {@see phoneObjectByUser()} 完成解密，
     * 业务侧无需再手写 `$union->phoneObjectByUser($user->channel, $encryptedData, $iv, $user->openId)`。
     *
     * @param string $encryptedData 客户端回传的加密数据
     * @param string $iv            加密初始向量
     *
     * @throws InvalidArgumentException 该 UnionUser 所属渠道不支持 encryptedData 解密获取手机号
     */
    public function phoneObjectForUser(UnionUser $user, string $encryptedData, string $iv): UnionPhone
    {
        return $this->phoneObjectByUser($user->channel, $encryptedData, $iv, $user->openId);
    }

    /**
     * 从已登录的 UnionUser 一键解密用户资料（桥接入口，免重复传参）
     *
     * 与 {@see phoneObjectForUser()} 同理：从 {@see UnionUser} 取回 channel / openId 后复用
     * {@see userInfoObjectByUser()}。unionId 一并透传（若该 UnionUser 已携带开放平台 unionId）。
     *
     * @param string $encryptedData 客户端回传的加密数据
     * @param string $iv            加密初始向量
     *
     * @throws InvalidArgumentException 该 UnionUser 所属渠道不支持 encryptedData 解密获取用户资料
     */
    public function userInfoObjectForUser(UnionUser $user, string $encryptedData, string $iv): UnionUser
    {
        $unionId = $user->unionId !== '' ? $user->unionId : null;

        return $this->userInfoObjectByUser($user->channel, $encryptedData, $iv, $user->openId, $unionId);
    }

    /**
     * 统一「encryptedData 解密」分派（data / phone / userInfo 共用）
     *
     * @return array{0:string, 1:class-string<WechatApp>|class-string<DouyinApp>|class-string<BaiduApp>|class-string<LarkApp>|class-string<QqApp>|class-string<WechatWorkApp>}
     */
    private function decryptChannel(Channel $channel): array
    {
        return match ($channel) {
            Channel::WechatMini, Channel::WechatMp, Channel::WechatApp => ['wechat', WechatApp::class],
            Channel::DouyinMini, Channel::DouyinMp => ['douyin', DouyinApp::class],
            Channel::BaiduMini => ['baidu', BaiduApp::class],
            Channel::Lark => ['lark', LarkApp::class],
            Channel::Qq => ['qq', QqApp::class],
            Channel::WechatWork => ['wechat_work', WechatWorkApp::class],
            default => throw new InvalidArgumentException(
                "渠道 [{$channel->value}] 暂不支持 encryptedData 解密（手机号 / 用户资料）",
            ),
        };
    }

    /**
     * 获取支付适配器（kode/pays 为唯一支付实现）
     *
     * 2.0 起支付能力完全委托企业级聚合支付 SDK kode/pays。需先
     * `composer require kode/pays`；未安装时调用即抛清晰异常，引导安装。
     * 付款人身份（openid / buyer_id）由桥接自动从已登录的 {@see UnionUser} 注入。
     *
     * @see \Kode\MiniApp\Union\Bridge\PaysBridge
     */
    public function pay(Channel $channel): PayAdapter
    {
        return $this->payAdapter($channel);
    }

    /**
     * 获取高级支付能力适配器（分账 / 转账 / 对账）
     *
     * 在 {@see self::pay()} 返回的核心 {@see PayAdapter} 之上，进一步返回实现了
     * {@see AdvancedPayAdapter} 的实例，从而可调用分账 / 转账 / 对账等 kode/pays 网关特色方法。
     * 当前唯一实现为 {@see \Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter}（kode/pays 桥接）；
     * 若某渠道的支付适配器未实现该接口，调用会抛清晰异常引导接入 kode/pays。
     *
     * @see \Kode\MiniApp\Union\Bridge\PaysBridge
     */
    public function advancedPay(Channel $channel): AdvancedPayAdapter
    {
        $adapter = $this->payAdapter($channel);
        if (!$adapter instanceof AdvancedPayAdapter) {
            throw new \RuntimeException(
                "渠道 [{$channel->label()}] 的支付适配器不支持高级能力（分账 / 转账 / 对账），"
                . '请使用 kode/pays 桥接（PaysBridgePayAdapter）',
            );
        }

        return $adapter;
    }

    /**
     * 获取回调适配器
     *
     * 2.0 起回调验签 / 解密完全委托 kode/pays（{@see PaysBridgeNotifyAdapter}）。
     * 需先 `composer require kode/pays`；未安装时调用即抛清晰异常，引导安装。
     *
     * @see \Kode\MiniApp\Union\Bridge\PaysBridge
     */
    public function notify(Channel $channel): NotifyAdapter
    {
        return $this->notifyAdapter($channel);
    }

    /**
     * 获取 Webhook 事件回调适配器
     *
     * 与 {@see self::notify()}（同步支付结果通知）不同，本方法面向平台异步推送的「事件型 Webhook」
     * （订阅续费、退款状态、转账到账、争议 / 拒付等）。返回的 {@see WebhookAdapter} 委托 kode/pays
     * 网关的 WebhookCapableInterface 完成验签 + 解析，需先 `composer require kode/pays`。
     *
     * @see \Kode\MiniApp\Union\Bridge\PaysBridge
     */
    public function webhook(Channel $channel): WebhookAdapter
    {
        return $this->webhookAdapter($channel);
    }

    /**
     * 获取退款能力适配器（申请 / 查询 / 取消退款）
     *
     * 与 {@see self::pay()}（下单）/ {@see self::notify()}（同步验签）/ {@see self::webhook()}
     * （异步事件）不同，本方法面向「退款闭环」：返回的 {@see RefundAdapter} 委托 kode/pays 网关的
     * RefundCapableInterface 完成 applyRefund / queryRefund / cancelRefund，需先
     * `composer require kode/pays`。
     *
     * @see \Kode\MiniApp\Union\Bridge\PaysBridge
     */
    public function refund(Channel $channel): RefundAdapter
    {
        return $this->refundAdapter($channel);
    }

    /**
     * 获取加密货币支付适配器（Coinbase 等聚合网关）
     *
     * 与 {@see self::pay()}（法币下单）/ {@see self::refund()}（法币退款）不同，本方法面向
     * 「加密货币支付」：返回的 {@see CryptoAdapter} 委托 kode/pays 网关的 CryptoCapableInterface
     * 完成 createCryptoOrder / getPaymentAddresses / getExchangeRate / getConfirmations / 退款 /
     * 异步验签，需先 `composer require kode/pays`。
     *
     * 加密货币不在 miniapp Kernel 默认渠道凭证体系内，故优先使用：
     *  - 已通过 {@see self::registerCryptoAdapter()} 注册的适配器；或
     *  - `Union::crypto(channel, resolver)` 注入自定义 config resolver。
     * 否则抛清晰异常引导接入。
     *
     * @see \Kode\MiniApp\Union\Bridge\PaysBridge
     */
    public function crypto(Channel $channel, ?\Closure $resolver = null): CryptoAdapter
    {
        return $this->cryptoAdapter($channel, $resolver);
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
     * 渠道能力发现
     *
     * 返回某渠道支持的能力（登录 / 支付 / 回调 / 用户资料 / 解密）以及
     * 启用这些能力所需的必填配置键，便于运行前自检：
     *
     *   $info = Union::capabilities(Channel::WechatMini);
     *   $info->supports(ChannelFeature::Pay);          // true
     *   $info->requiredConfig;                          // ['app_id','mch_id',...]
     *   $info->toArray();
     *
     * 能力集合如实反映当前适配器实现覆盖（例如微信 H5 / PC 暂未实现支付）。
     */
    public function capabilities(Channel $channel): CapabilityInfo
    {
        $provider = $this->kernelProvider($channel->providerKey());
        $config   = $provider->config();

        $keys = $config->requiredKeys();
        foreach ($channel->features() as $feature) {
            $keys = [...$keys, ...$config->requiredKeysFor($feature)];
        }
        $keys = array_values(array_unique($keys));

        return new CapabilityInfo($channel, $channel->features(), $keys);
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

    /**
     * 注册自定义退款适配器
     */
    public function registerRefundAdapter(RefundAdapter $adapter): void
    {
        $this->refundAdapters[$adapter->channel()->value] = $adapter;
    }

    /**
     * 注册自定义加密货币适配器
     */
    public function registerCryptoAdapter(CryptoAdapter $adapter): void
    {
        $this->cryptoAdapters[$adapter->channel()->value] = $adapter;
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
            if (!PaysBridge::available()) {
                throw new \RuntimeException(
                    '支付能力已迁移至 kode/pays，请先执行 `composer require kode/pays` 后再调用 Union::pay()'
                );
            }
            $this->payAdapters[$key] = PaysBridge::adapterForKernel($channel, $this->kernel);
        }
        return $this->payAdapters[$key];
    }

    private function notifyAdapter(Channel $channel): NotifyAdapter
    {
        $key = $channel->value;
        if (!isset($this->notifyAdapters[$key])) {
            if (!PaysBridge::available()) {
                throw new \RuntimeException(
                    '支付回调验签已迁移至 kode/pays，请先执行 `composer require kode/pays` 后再调用 Union::notify()'
                );
            }
            $this->notifyAdapters[$key] = PaysBridge::notifyAdapterForKernel($channel, $this->kernel);
        }
        return $this->notifyAdapters[$key];
    }

    private function webhookAdapter(Channel $channel): WebhookAdapter
    {
        $key = $channel->value;
        if (!isset($this->webhookAdapters[$key])) {
            if (!PaysBridge::available()) {
                throw new \RuntimeException(
                    'Webhook 事件验签已迁移至 kode/pays，请先执行 `composer require kode/pays` 后再调用 Union::webhook()'
                );
            }
            $this->webhookAdapters[$key] = PaysBridge::webhookAdapterForKernel($channel, $this->kernel);
        }
        return $this->webhookAdapters[$key];
    }

    private function refundAdapter(Channel $channel): RefundAdapter
    {
        $key = $channel->value;
        if (!isset($this->refundAdapters[$key])) {
            if (!PaysBridge::available()) {
                throw new \RuntimeException(
                    '退款能力已迁移至 kode/pays，请先执行 `composer require kode/pays` 后再调用 Union::refund()'
                );
            }
            $this->refundAdapters[$key] = PaysBridge::refundAdapterForKernel($channel, $this->kernel);
        }
        return $this->refundAdapters[$key];
    }

    private function cryptoAdapter(Channel $channel, ?\Closure $resolver): CryptoAdapter
    {
        $key = $channel->value;
        if (isset($this->cryptoAdapters[$key])) {
            return $this->cryptoAdapters[$key];
        }

        if ($resolver !== null) {
            if (!PaysBridge::available()) {
                throw new \RuntimeException(
                    '加密货币能力已迁移至 kode/pays，请先执行 `composer require kode/pays` 后再调用 Union::crypto()'
                );
            }
            return PaysBridge::cryptoAdapter($channel, $resolver);
        }

        if (!PaysBridge::available()) {
            throw new \RuntimeException(
                '加密货币能力已迁移至 kode/pays，请先执行 `composer require kode/pays` 后再调用 Union::crypto()'
            );
        }

        // 默认 Kernel resolver 对加密货币渠道会抛清晰引导（Kernel 无 crypto platform 配置）；
        // 业务侧应改用 Union::crypto(channel, resolver) 或 registerCryptoAdapter()。
        return PaysBridge::cryptoAdapterForKernel($channel, $this->kernel);
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
            default => throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 不支持登录适配器（加密货币等支付专用渠道无登录能力）",
            ),
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
            default => throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 不支持用户资料适配器（加密货币等支付专用渠道无用户资料能力）",
            ),
        };

        if (!class_exists($adapter)) {
            throw new InvalidArgumentException(
                "渠道 [{$channel->label()}] 的用户资料适配器尚未实现：{$adapter}"
            );
        }

        return new $adapter($this->kernel);
    }
}
