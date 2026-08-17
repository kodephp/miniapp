# Kode MiniApp

多平台小程序、公众号、企业号统一接入 SDK。

![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)
![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net/)
![Union](https://img.shields.io/badge/union-统一入口-success.svg)

## 特性

- **Union 统一调用入口**（推荐）：业务侧只需要 `use Kode\MiniApp\Union\Union;` 一行代码，通过 `Union::wechat()->mini($code)` 一行完成跨平台登录、支付、回调，跨端账号自动合并（UnionID）
- **多端登录约束（SessionManager）**：内置 `SessionManager` 提供 4 种登录约束策略（多端可登录 / 单设备单账号 / 单账号单端 / 单账号全端），可应对优酷/腾讯视频/银行 App 等"禁止账号共享"场景
- **多平台统一接入**：一套代码对接微信、支付宝、抖音、百度、QQ、企业微信、钉钉、飞书
- **微信生态互联**：通过微信开放平台（Component）统一管理公众号、小程序、移动 App、PC 网站应用，账号体系（UnionID）互通
- **PHP 8.3+ 现代化**：使用 readonly、enum、match、构造函数属性提升、nullsafe、命名参数、`__call`/`__callStatic` 魔术分发等新特性
- **企业级能力**：除 C 端小程序外，完整支持企业微信、钉钉、飞书的通讯录、审批、消息推送
- **服务端消息处理**：统一处理各平台的消息推送和事件回调
- **企业级支付**：2.0 起支付能力完全由 `kode/pays` 承载（composer 硬依赖），下单 / 验签 / 退款 / 分账 / 转账 / 对账统一经 kode/pays，付款人身份（openid / buyer_id）由本包登录后自动注入
- **工具桥接**：内置基础工具类，同时可桥接到 `kode/tools` 企业级工具包
- **异常桥接**：内置异常体系，同时可桥接到 `kode/exception` 统一异常处理组件
- **Kode 生态兼容**：与 kode/pays、kode/tools、kode/exception、kode/cache、kode/event、kode/jwt 等包无缝协作
- **统一 API 响应与业务异常**：`ApiResponse` 归一化各平台异构错误字段（errcode / err_no / errno / code / 支付宝 xxx_response.code / OAuth error），`ApiException` 提供令牌失效、频率限制、可重试分类，业务侧可据此自动清缓存 / 退避
- **AccessToken 自动缓存**：基于 PSR-16 的令牌缓存 + 单飞锁（single-flight）防击穿，带安全边界提前过期，避免每次调用重复换取触发平台配额
- **HTTP 重试与日志脱敏**：可配置指数退避重试（遵循 Retry-After）、自定义中间件注入，敏感字段（secret / access_token / sign 等）在日志中自动掩码

## v1.14.0 核心增强

- 新增 `ApiResponse` / `ApiException` / `TokenManager` / `RetryPolicy` / `LogSanitizer` / `ArrayCache` 等核心组件，统一响应解析、令牌缓存、请求重试与日志脱敏。
- 支付宝统一网关 `AlipayGateway` 收敛散落的签名逻辑，并修正签名串剔除空值 / 顶层参数误入 `biz_content` 两处缺陷，私钥兼容 PKCS#1 与 PKCS#8。
- 9 个平台 Auth 模块接入统一响应与令牌缓存，最低 PHP 要求保持 `>= 8.3`。

## Kode 生态关联

Kode MiniApp 是 Kode 生态的重要组成部分，与以下包可协同工作：

| 包名 | 类型 | 说明 |
|------|------|------|
| `kode/pays` | require（硬依赖） | 企业级多平台聚合支付 SDK，2.0 起为**唯一**支付路径（下单 / 验签 / 退款 / 分账 / 转账 / 对账），付款人身份由本包登录后注入 |
| `kode/tools` | suggest | PHP 通用工具包（加解密、二维码、消息体等），安装后自动优先使用 |
| `kode/exception` | suggest | 统一异常处理组件，安装后扩展异常码体系 |
| `kode/cache` | suggest | 高性能缓存组件，支持 Redis/Memcached 等，SessionManager 默认基于 PSR-16 |
| `kode/event` | suggest | 轻量级事件编排库 |
| `kode/jwt` | 可选 | JWT 签发/验证，建议与 SessionManager 配合使用（见下文） |

```bash
# 安装核心包
composer require kode/miniapp

# 按需安装生态包（推荐）
composer require kode/pays      # 企业级支付
composer require kode/tools     # 工具包
composer require kode/exception # 异常处理
composer require kode/cache     # 缓存
composer require kode/event     # 事件
```

## 支持平台

| 平台 | 标识 | 类型 | 能力 | 详细文档 |
|------|------|------|------|----------|
| 微信 | `wechat` | C端 | 登录、JS-SDK、用户、素材、菜单、客服、消息、订阅消息、小程序码、数据分析、支付、订单物流同步、内容安全、URL Scheme/Link、插件管理、直播、附近小程序、门店、卡券、摇一摇、发票、连Wi-Fi、微信小店、红包、广告、即时配送、搜一搜、动态消息、设备功能、云开发、服务端、回调通知 | [查看](docs/wechat.md) |
| 微信开放平台 | `wechat_open` | C端 | 第三方平台代公众号 / 小程序、Component AccessToken、PreAuthCode、授权流程、消息加解密、移动 App / PC 网站应用登录与支付 | [查看](docs/wechat-open.md) |
| 支付宝 | `alipay` | C端 | 登录、支付、转账、账单、营销、会员、回调通知 | [查看](docs/alipay.md) |
| 抖音 | `douyin` | C端 | 登录、支付、视频、评论 | [查看](docs/douyin.md) |
| 百度 | `baidu` | C端 | 登录、支付、模板消息 | [查看](docs/baidu.md) |
| QQ | `qq` | C端 | 登录、支付 | [查看](docs/qq.md) |
| 微信企业号 | `wechat_work` | B端 | 认证、通讯录、部门管理、客户联系、外部联系人、标签、消息、审批、素材管理、应用管理、OA打卡汇报、会议室、公费电话、日程、收集表、微盘、上下游、会话存档、服务端、回调通知 | [查看](docs/wechat-work.md) |
| 钉钉 | `dingtalk` | B端 | 认证、通讯录、消息、审批、群机器人、考勤、智能人事、日志、项目、智能工作流 | [查看](docs/dingtalk.md) |
| 飞书 | `lark` | B端 | 认证、通讯录、消息、审批、审批定义、多维表格、文档、日历、任务、知识库、邮件 | [查看](docs/lark.md) |

## 能力支持矩阵

> 标注「✅ 支持 / — 不适用或暂未支持」。客户端敏感数据解密与手机号获取的算法细节见下文「统一敏感数据」与 [docs/union.md](docs/union.md)。

| 平台 | 登录 | 用户资料 | 客户端解密(encryptedData) | 手机号(code 换) | 手机号(encryptedData) | 支付 | 回调通知 |
|------|------|----------|---------------------------|-----------------|----------------------|------|----------|
| 微信（小程序 / 公众号 / H5 / PC / App） | ✅ | ✅ | ✅ | ✅ 小程序 | ✅ | ✅ 全端(JSAPI/APP/MWEB/NATIVE) + 服务商 | ✅ 全场景 |
| 微信开放平台 | ✅ | ✅ | — | — | — | — | — |
| 支付宝（小程序 / 生活号 / App） | ✅ | ✅ | ✅ response+sign | — | — | ✅ mini/mp/app | ✅ |
| 抖音（小程序） | ✅ | ✅ | ✅ | ✅ RSA 密文 | ✅ | ✅ 小程序 | ✅ 小程序 |
| 百度（小程序） | ✅ | ✅ | ✅ | — | ✅ | ✅ 小程序 | ✅ 小程序 |
| QQ（小程序） | ✅ | ✅ | ✅ | — | ✅ | ✅ 小程序 | ✅ 小程序 |
| 企业微信 | ✅ | ✅ | ✅ | — | ✅ | —（经 kode/pays） | ✅ |
| 钉钉 | ✅ | ✅ | — | — | — | — | — |
| 飞书 | ✅ | ✅ | ✅ hex 变体 | — | ✅ | — | — |

说明：

- **客户端解密(encryptedData)**：微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 走统一 `Union::decrypt()` / `decryptByUser()`（AES-128-CBC + watermark）；支付宝走 `Union::alipay()->decrypt()`（response+sign / RSA2 验签），不并入统一入口。
- **手机号(code 换)**：微信小程序 `Union::phoneByCode()`（明文）；抖音 `Union::phoneByCode()`（RSA 密文，需 `app_private_key`）。
- **手机号(encryptedData)**：微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 走 `Union::phoneByDecrypt()` / `phoneByUser()`；支付宝走 `Union::phoneByResponse()`。
- **支付 / 回调**：B 端平台（钉钉 / 飞书）及微信开放平台（第三方平台）无消费者支付场景，标记为「—」属设计预期。

## 高级支付能力矩阵（2.0 · kode/pays 桥接）

> 2.0 起支付完全由 `kode/pays` 承载。`Union::xxx()->advancedPay()` / `paymentCapabilities()` / `capabilityProfile()` 暴露 10 项高级能力（分账 / 转账 / 对账 / 红包 / 订阅 / 余额 / 结算 / 个人收款 / Webhook / 退款）。能力声明严格镜像 kode/pays 真实网关实现（零漂移，由 `PaysBridgeCapabilityMatrixConsistencyTest` 守护）。
>
> 完整矩阵、跨渠道真实网关签名链 e2e 验证状态与「大声失败」契约见 **[docs/CAPABILITY_MATRIX.md](docs/CAPABILITY_MATRIX.md)**。

| 能力 | 微信 V2 | 支付宝 | 抖音 | QQ |
|------|:-------:|:------:|:----:|:---:|
| 分账 / 转账 / 红包 / 订阅 / 对账 / 结算 / 个人收款 / 退款 | ✅ | ✅ | 分账 ✅、退款 ✅（其余 ❌） | 退款 ✅（其余 ❌） |
| 余额 | ❌ | ✅ | ❌ | ❌ |
| Webhook 事件 | ✅ | ✅ | ✅ | ✅ |

跨渠道签名对照：核心下单生命周期 + 高级能力在 **微信 V2（XML+MD5）× 支付宝（RSA2）双渠道均已 e2e 验证**；微信 V3 入站解密 / 出站签名 / Webhook 验签双链均补齐。

## 安装

```bash
composer require kode/miniapp
```

## 快速开始

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'wechat' => [
        'app_id'  => 'wx123...',
        'secret'  => 'abc...',
        'mch_id'  => '123...',
    ],
    'alipay' => [
        'app_id'      => '2024...',
        'private_key' => '...',
        'public_key'  => '...',
    ],
]);

// 微信登录
$session = $kernel->wechat()->app()->auth()->session($code);

// 微信下单（2.0 起支付完全由 kode/pays 承载，openid 自动注入）
$user  = $kernel->union()->wechat()->mini($code);
$order = $kernel->union()->wechat()->pay()->createOrder([
    'out_trade_no' => 'ORDER_001',
    'description'  => '测试商品',
    'amount'       => ['total' => 9999],  // 单位：分
], $user);

// 支付宝下单（同样经 kode/pays，buyer_id 自动注入）
$order = $kernel->union()->alipay()->createOrder([
    'out_trade_no' => 'ORDER_001',
    'total_amount' => '99.99',
    'subject'      => '测试商品',
]);

// 高级支付能力（分账 / 转账 / 对账）：advancedPay() 返回 AdvancedPayAdapter，
// 方法名与 kode/pays 网关契约一致
$adv = $kernel->union()->wechat()->advancedPay();
$adv->profitSharingCreate([
    'transaction_id' => '微信订单号',
    'out_order_no'   => '商户分账单号',
    'receivers'      => [['type' => 'MERCHANT_ID', 'account' => 'mch_2', 'amount' => 100]],
]);
$adv->transferSingle(['out_biz_no' => 'BIZ_1', 'amount' => 100, 'recipient' => ['type' => 'openid', 'account' => $openId, 'name' => '张三']]);
$bill = $adv->reconciliationDownloadBill(['bill_date' => '20260814']);

// 能力菜单发现（无需支付配置，基于 kode/pays 网关类能力发现）：
// 门面级 paymentCapabilities() 等价于 advancedPay()->paymentCapabilities()，
// 适合按渠道动态渲染能力菜单（无需先持有高级适配器实例）
$caps = $kernel->union()->wechat()->paymentCapabilities();
// => ['profit_sharing' => true, 'transfer' => true, ... 'balance' => false, 'webhook' => false]
if ($caps['red_packet']) {
    // 仅微信 V2 支持红包，V3 为 false
}

// 完整能力画像（单调用合并基础能力 + 高级支付 10 项开关，前端一次性渲染能力菜单）：
$profile = $kernel->union()->wechat()->capabilityProfile();
// $profile['features'] 基础能力；$profile['payment'] 高级支付 10 项布尔开关

// Webhook 异步事件回调（与 notify() 同步结果通知对称）：webhook() 返回 WebhookAdapter。
// 需 kode/pays >= 2.6.0（WebhookCapableInterface）；2.3.0 上 supportsWebhook() 返回 false。
$wh = $kernel->union()->wechat()->webhook();
if ($wh->verify($rawBody, $headers)) {
    $event = $wh->parse($rawBody);   // ['gateway'=>..., 'event_type'=>..., 'data'=>...]
}

// 退款闭环（申请 / 查询 / 取消）：refund() 返回 RefundAdapter，对齐 RefundCapableInterface。
// 与 PayAdapter::refund()（仅申请）相比，额外覆盖 queryRefund / cancelRefund。
$refund = $kernel->union()->wechat()->refund();
$refund->applyRefund([
    'out_trade_no'  => 'ORDER_001',
    'out_refund_no' => 'REFUND_001',
    'amount'        => 100,
]);
$refund->queryRefund('REFUND_001');          // 按商户退款单号查询
$refund->cancelRefund('REFUND_001');         // 仅部分网关支持（如 Stripe）

// 个人收款（收款码 / 提现，PersonalReceiveCapableInterface）
$pr = $kernel->union()->wechat()->advancedPay();
$pr->personalReceiveCreateQrCode(['amount' => 100, 'description' => '货款']);
$pr->personalReceiveQueryRecords(['start_time' => '2026-08-01', 'page' => 1]);

// 加密货币支付（Coinbase 等聚合网关，CryptoCapableInterface）
// 加密货币不在 miniapp Kernel 默认凭证体系内，须注入自定义 config resolver
use Kode\MiniApp\Union\Channel;
$crypto = $kernel->union()->crypto(Channel::Crypto, fn () => ['api_key' => '...']);
$order  = $crypto->createCryptoOrder([
    'crypto_currency' => 'BTC',
    'fiat_amount'     => 100,
    'fiat_currency'   => 'USD',
]);
$rate   = $crypto->getExchangeRate('BTC', 'USD');   // 实时汇率
```

## 架构设计

```
Kernel（门面）
  ├── Provider（平台入口）           ←  底层细粒度接口
  │     └── App（应用实例）
  │           ├── Auth（认证）
  │           ├── PayBridge（桥接 kode/pays，企业级支付）
  │           ├── Message（消息）
  │           ├── Contact（通讯录）
  │           ├── Approval（审批）
  │           ├── Jssdk（JS-SDK）
  │           ├── Server（服务端处理器）
  │           └── Notify（回调通知处理器）
  │
  └── union() ─→ Union（统一入口）   ←  业务侧推荐使用
        ├── Channel 枚举（跨平台渠道）
        ├── LoginAdapter（统一登录）
        ├── UserAdapter（统一用户资料）
        ├── PayAdapter（统一支付）
        └── NotifyAdapter（统一回调）
```

### 核心组件

- **Kernel**：统一门面，通过 `$kernel->wechat()`、`$kernel->dingtalk()` 等快捷方法获取平台实例
- **Provider**：平台入口，管理配置和 HTTP 客户端，支持多应用实例
- **App**：应用实例，聚合该平台的所有能力模块
- **Server**：服务端消息处理器，统一处理各平台的消息推送和事件回调
- **Message**：消息构造器，构造被动回复消息
- **Notify**：支付回调通知处理器，自动验签并触发业务逻辑
- **Union**（推荐）：统一入口门面，通过 `Channel` 枚举 + 适配器屏蔽各平台差异
- **PayBridge**：支付桥接器，自动检测并桥接到 `kode/pays` 企业级支付 SDK
- **ToolsBridge**：工具桥接器，自动检测并优先使用 `kode/tools` 工具类
- **ExceptionBridge**：异常桥接器，自动检测并扩展 `kode/exception` 异常码体系

## 各平台详细文档

每个平台都有独立的详细使用文档，包含配置说明和各功能模块的完整使用示例：

| 平台 | 文档路径 | 说明 |
|------|----------|------|
| **Union 统一入口** | [docs/union.md](docs/union.md) | 跨平台一键登录 / 支付 / 回调，跨端账号自动合并（**推荐阅读**） |
| 微信 | [docs/wechat.md](docs/wechat.md) | 公众号/小程序，30+ 功能模块 |
| 微信开放平台 | [docs/wechat-open.md](docs/wechat-open.md) | 第三方平台（代公众号 / 小程序），App / PC 互联 |
| 微信企业号 | [docs/wechat-work.md](docs/wechat-work.md) | 企业微信，通讯录/审批/客户联系/会话存档 |
| 支付宝 | [docs/alipay.md](docs/alipay.md) | 小程序/生活号，支付/转账/营销 |
| 抖音 | [docs/douyin.md](docs/douyin.md) | 小程序，视频管理/支付 |
| 百度 | [docs/baidu.md](docs/baidu.md) | 小程序，登录/支付/模板消息 |
| QQ | [docs/qq.md](docs/qq.md) | 小程序，登录/支付 |
| 钉钉 | [docs/dingtalk.md](docs/dingtalk.md) | 企业办公，通讯录/审批/考勤/智能人事 |
| 飞书 | [docs/lark.md](docs/lark.md) | 企业办公，通讯录/审批/多维表格/文档/日历 |

---

## 各平台详细配置

### 微信

```php
'wechat' => [
    'app_id'     => 'wx1234567890',
    'secret'     => 'your-secret',
    'mch_id'       => '1234567890',
    'api_v3_key'   => 'your-api-v3-key',
    'cert_path'    => '/path/to/apiclient_cert.pem',
    'key_path'     => '/path/to/apiclient_key.pem',
    'mch_serial_no' => '商户 API 证书序列号',  // 微信支付 V3 请求签名必填
    'token'      => 'your-token',
    'aes_key'    => 'your-aes-key',
]
```

### 微信开放平台

```php
'wechat_open' => [
    'component_appid'      => 'wxcomp0000000000',  // 第三方平台 AppID
    'component_secret'     => 'component-secret',  // 第三方平台 AppSecret
    'token'                => 'your-token',        // 消息校验 Token
    'encoding_aes_key'     => str_repeat('a', 43), // 43 位消息加解密 Key
    // 可选：移动 / PC 应用配置（Union 入口自动读取）
    'mobile_app_id'        => 'wxapp0000000000',   // 移动应用 AppID
    'mobile_app_secret'    => 'mobile-secret',
    'site_app_id'          => 'wxapp0000000001',   // PC 网站应用 AppID
    'site_app_secret'      => 'site-secret',
]
```

### 支付宝

```php
'alipay' => [
    'app_id'      => '2024...',
    'private_key' => 'your-private-key',
    'public_key'  => 'alipay-public-key',
    'sandbox'     => false,
]
```

### 抖音

```php
'douyin' => [
    'app_id'    => 'tt...',
    'secret'    => 'your-secret',
    'salt'      => 'your-salt',
    'mch_id'    => 'your-mch-id',
    'pay_token' => 'your-pay-token',
]
```

### 百度

```php
'baidu' => [
    'app_id'  => 'your-app-id',
    'secret'  => 'your-secret',
    'deal_id' => 'your-deal-id',
    'pay_key' => 'your-pay-key',
]
```

### QQ

```php
'qq' => [
    'app_id'  => 'your-app-id',
    'secret'  => 'your-secret',
]
```

### 微信企业号

```php
'wechat_work' => [
    'corp_id'        => 'ww...',
    'secret'         => 'your-secret',
    'agent_id'       => '1000002',
    'contact_secret' => 'contact-secret',
    'token'          => 'your-token',
    'aes_key'        => 'your-aes-key',
]
```

### 钉钉

```php
'dingtalk' => [
    'app_id'     => 'ding...',
    'app_key'    => 'your-app-key',
    'app_secret' => 'your-app-secret',
    'agent_id'   => '123456',
]
```

### 飞书

```php
'lark' => [
    'app_id'             => 'cli_...',
    'secret'             => 'your-secret',
    'is_feishu'          => true,  // true=国内版, false=国际版
    'encrypt_key'        => 'your-encrypt-key',
    'verification_token' => 'your-token',
]
```

## 平台能力使用

### 快速开始（5 行代码接入全平台）

> 业务侧**只需要 `use Kode\MiniApp\Union\Union;` 一行代码**，通过静态方法即可访问所有平台。

```php
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Union\Union;

// 1. 初始化
$kernel = new Kernel([
    'wechat'       => [...],
    'wechat_open'  => [...],
    'alipay'       => [...],
    'douyin'       => [...],
    'baidu'        => [...],
    'qq'           => [...],
    'wechat_work'  => [...],
    'dingtalk'     => [...],
    'lark'         => [...],
]);
$kernel->union(); // 触发 Union 初始化（之后即可用静态方法）

// 2. 登录：跨平台统一返回 UnionUser
$user = Union::wechat()->mini('JS_CODE');            // 微信小程序
$user = Union::wechat()->mp('CODE');                 // 公众号 OAuth
$user = Union::wechat()->pc('PC_CODE');              // PC 扫码
$user = Union::alipay()->mini('AUTH_CODE');          // 支付宝小程序
$user = Union::douyin()->mini('CODE');               // 抖音小程序
$user = Union::work()->login('CODE');                // 企业微信
$user = Union::dingtalk()->mini('CODE');             // 钉钉
$user = Union::lark()->mini('CODE');                 // 飞书

// 3. 支付
$order = Union::wechat()->pay()->createOrder([
    'out_trade_no' => 'O001',
    'body'         => '商品',
    'total_fee'    => 100,
    'openid'       => $user->openId,
]);
$order = Union::alipay()->pay()->createOrder([...]); // 支付宝支付
$order = Union::work()->pay()->createOrder([...]);   // 企业微信支付

// 4. 回调
$data = Union::wechat()->notify()->decode($payload, $headers);
$data = Union::alipay()->notify()->decode($payload, $headers);

// 5. 用户资料（公众号 / H5 自动解析 mp access_token，无需手动传入）
$user = Union::wechat()->user($openId, [], 'mp');
//    小程序：传入客户端上报（已解密）的资料
$user = Union::wechat()->user($openId, ['raw' => $clientUserInfo], 'mini');
```

## 统一敏感数据（手机号 / 用户资料 / 加密数据）

小程序客户端回传的 `encryptedData`、手机号 code，经本 SDK 统一解密 / 校验后返回**强类型值对象**或**归一化数组**，业务侧无需关心各端算法差异（AES-128-CBC + watermark / 支付宝 RSA2 / 抖音 RSA 密文）。登录成功即自动托管 `session_key`，后续解密可一键取用，无需手动传递密钥。

### 三族统一入口

| 能力 | 数组入口 | 值对象入口 | 覆盖渠道 |
|------|----------|------------|----------|
| 通用加密数据 data | `Union::decrypt()` / `decryptByUser()` | — | 微信/抖音/QQ/百度/飞书/企业微信（支付宝走 `Union::alipay()->decrypt()`） |
| 手机号 phone | `Union::phoneByCode()` / `phoneByDecrypt()` / `phoneByUser()` / `phoneByResponse()` | `Union::phoneObjectBy*` | code：微信/抖音；encryptedData：微信/抖音/QQ/百度/飞书/企业微信；response：支付宝 |
| 用户资料 userInfo | `Union::userInfoByDecrypt()` / `userInfoByUser()` | `Union::userInfoObjectBy*` | 微信/抖音/QQ/百度/飞书/企业微信 |

### 典型用法

```php
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\Channel;

// 1. 登录即自动托管 session_key（微信/抖音/QQ/百度/飞书/企业微信）
$user = Union::wechat()->mini('JS_CODE');

// 2. 手机号：新版 code 换手机号（微信小程序）
$phone = Union::phoneByCode(Channel::WechatMini, 'PHONE_CODE');
//    或强类型值对象：$phone->phoneNumber / $phone->purePhoneNumber / $phone->countryCode
$phoneObj = Union::phoneObjectByCode(Channel::WechatMini, 'PHONE_CODE');

// 3. 手机号：encryptedData + session_key（微信/抖音/QQ/百度/飞书/企业微信）
$phone = Union::phoneByDecrypt(Channel::WechatMini, $encryptedData, $sessionKey, $iv);
//    或一键取用登录托管的 session_key（免手动传密钥）
$phone = Union::phoneByUser(Channel::WechatMini, $encryptedData, $iv, $user->openId);

// 4. 支付宝手机号（response + sign，RSA2 验签防篡改）
$phone = Union::phoneByResponse(Channel::AlipayMini, $response, $sign);

// 5. 从已登录 UnionUser 一键解密（桥接入口，免重复传参）
$phoneObj = Union::phoneObjectForUser($user, $encryptedData, $iv);
$profile  = Union::userInfoObjectForUser($user, $encryptedData, $iv);

// 6. 客户端加密用户资料
$profile = Union::userInfoByDecrypt(Channel::WechatMini, $encryptedData, $sessionKey, $iv);
```

> 完整算法说明、失败语义（统一抛 `ApiException`）、各端字段差异对照表见 [docs/union.md](docs/union.md)。
> 敏感密钥（`session_key` / `aes_key`）已在日志脱敏键中，严禁下发前端或写入日志。

### 多端登录约束（SessionManager）

> **业务场景**：1 个用户可能从小程序、APP、PC、公众号等多个端口登录。
> 某些业务（优酷/腾讯视频/银行 App）需要约束登录端口，防止账号共享或设备被顶替。

SDK 内置 `SessionManager` 提供 4 种登录约束策略：

| 策略 | 含义 | 适用场景 |
|------|------|----------|
| `Multi` | 多端可同时登录（默认） | 通用应用 |
| `SingleEnd` | 单设备单账号（同设备只能登录 1 个账号） | 共享设备、家庭账户 |
| `SingleUser` | 单账号单端（同账号同端口重复登录踢旧） | 跨端允许，限同端重复 |
| `SingleAll` | 单账号全端（同账号只能登录 1 次） | 优酷、腾讯视频、银行 App |

#### 与 `kode/jwt` 的关系（设计边界）

| 关注点 | 归属 |
|--------|------|
| Token 签发 / 验证 / 刷新 | `kode/jwt`（stateless） |
| Token 黑名单 / 撤销 | `kode/jwt`（通过 jti） |
| 登录约束 / 多端踢人 | **`kode/miniapp` SessionManager**（stateful） |
| 业务侧权限 / 角色 | 业务侧 |

**业务侧典型组合用法**：

```php
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Session\SessionManager;
use Kode\MiniApp\Session\CacheSessionStorage;
use Kode\MiniApp\Session\SessionPolicy;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\Channel;

// 1. 初始化
$kernel = new Kernel(['wechat' => [...], 'alipay' => [...]]);
$kernel->union();

// 2. 接入 SessionManager（选一种存储，推荐 Redis）
$session = new SessionManager(
    new CacheSessionStorage($redisCache),         // PSR-16 Cache
    SessionPolicy::SingleAll,                     // 强制单账号全端
    86400 * 30                                    // 30 天过期
);
$kernel->union()->withSession($session);

// 3. 业务侧登录（自动创建 session 并应用登录约束）
$user = Union::wechat()->mini('JS_CODE');  // 同账号再次登录会自动踢掉之前所有 session

// 4. 业务侧用 session.id 作为 JWT jti 签发 token
$token = $jwt->issue(['jti' => $session->id, 'sub' => $user->unionId]);

// 5. 验证 token 时，校验 session 是否还有效
$session = $sessionManager->get($jwt->jti);
if ($session === null) {
    throw new UnauthorizedException('会话已失效');
}
```

#### 四种策略对比

```php
// 场景：用户在三个设备登录了同一账号
//   - iPhone 小程序 (unionId=u001)
//   - Android 小程序 (unionId=u001)
//   - PC 公众号 (unionId=u001)

// Multi: 全部允许
// → 最终 3 个 session 都有效

// SingleEnd: 同设备只能登录 1 个账号
// → iPhone Android PC 各自独立（不冲突），但同设备重复登录会踢

// SingleUser: 同账号同端口只允许 1 个 session
// → 3 个都有效（小程序和公众号是不同端口），但同端口重复登录会踢

// SingleAll: 强制全端唯一
// → 只有最后一个 session 有效（前面的都被踢）
// → 优酷/腾讯视频/银行 App 通常用这个
```

#### 显式创建 Session

```php
$user = Union::wechat()->mini('JS_CODE');

// 显式创建 session（可传入 client / clientId / ip 等）
$session = Union::wechat()->createSession(
    $user,
    scene:    'mini',
    client:   'ios',
    clientId: $deviceId,
    ip:       $request->ip(),
    userAgent:$request->userAgent(),
    payload:  ['role' => 'vip', 'level' => 5]
);

// 查询 session
$active = $session->get($sessionId);
$userSessions = $session->listByUnionId('u001');

// 主动踢人
$session->destroy($sessionId);              // 主动登出
$session->destroyByClient('u001', 'ios');   // 踢掉某设备
$session->destroyAll('u001');               // 踢掉所有端
```

#### Session 存储扩展

`SessionManager` 底层使用 PSR-16 Cache 接口，可注入任何实现：

```php
// Redis（推荐）
use Kode\Cache\Psr16\RedisCache;
$session = new SessionManager(
    new CacheSessionStorage(new RedisCache($redis))
);

// Memcached
use Symfony\Component\Cache\Psr16Adapter;
$session = new SessionManager(
    new CacheSessionStorage(new Psr16Adapter('memcached://localhost'))
);

// 文件（开发环境）
$session = new SessionManager(
    new CacheSessionStorage(new Symfony\Component\Cache\Psr16Adapter('file://' . __DIR__ . '/cache'))
);
```

### 核心设计：Union 静态门面

| 调用 | 等价写法 | 说明 |
|------|----------|------|
| `Union::wechat()` | `$kernel->union()->wechat()` | 微信生态聚合（公众号/小程序/H5/PC/App/开放平台） |
| `Union::wechatOpen()` | `$kernel->union()->wechatOpen()` | 微信开放平台（第三方平台） |
| `Union::alipay()` | `$kernel->union()->alipay()` | 支付宝聚合（小程序/生活号/App） |
| `Union::douyin()` | `$kernel->union()->douyin()` | 抖音聚合（小程序/头条号） |
| `Union::baidu()` | `$kernel->union()->baidu()` | 百度智能小程序 |
| `Union::qq()` | `$kernel->union()->qq()` | QQ 聚合 |
| `Union::wechatWork()` / `Union::work()` | `$kernel->union()->wechatWork()` | 企业微信聚合 |
| `Union::dingtalk()` | `$kernel->union()->dingtalk()` | 钉钉聚合 |
| `Union::lark()` | `$kernel->union()->lark()` | 飞书聚合 |

### 平台聚合类（Platform Union）

每个平台聚合类（[WechatUnion](file:///Users/Zhuanz/Desktop/website/composer/miniapp/src/Union/Platforms/WechatUnion.php)、[AlipayUnion](file:///Users/Zhuanz/Desktop/website/composer/miniapp/src/Union/Platforms/AlipayUnion.php) 等）提供以下统一方法：

#### 场景登录（最常用）

| 方法 | 适用场景 |
|------|----------|
| `->mini($code)` | 小程序 / 默认登录 |
| `->mp($code)` | 公众号 |
| `->h5($code)` | H5 |
| `->pc($code)` | PC 网站应用 |
| `->app($code)` | 移动 App |
| `->open($payload)` | 开放平台（authorization_code） |
| `->suite($code)` | 企业微信套件 |
| `->login($payload, $scene)` | 通用登录（自定义 payload + 场景） |

#### 四大能力

```php
$user = Union::wechat()->mini($code);                              // 1. 登录
$user = Union::wechat()->user($openId, $payload);                  // 2. 用户资料
$order = Union::wechat()->pay()->createOrder([...]);              // 3. 支付
$data = Union::wechat()->notify()->decode($payload, $headers);     // 4. 回调
```

#### 底层 Provider 访问

如需细粒度控制，可访问平台原始 Provider / App：

```php
$wechat = Union::wechat();

// 直接获取平台 App 实例（含全部 30+ 能力模块）
$app = $wechat->appInstance();
$app->message()->send($openId, 'text', 'Hello');
$app->media()->upload('image', '/path/to/image.jpg');
$app->menu()->create([...]);
$app->jssdk()->config($url, ['chooseImage']);
$app->subscribeMessage()->send($openId, $templateId, $data);
$app->miniProgramCode()->create('pages/index', ['id' => 1]);
$app->dataAnalysis()->dailySummary('20240101');

// 直接获取 Provider
$provider = $wechat->provider();
```

### 跨端账号合并（UnionID）

配置好微信开放平台（同一开放平台下绑定所有应用）后，跨端账号自动合并：

```php
// 用户先用小程序登录
$user1 = Union::wechat()->mini('JS_CODE');        // unionId: u001

// 再用 PC 扫码
$user2 = Union::wechat()->pc('PC_CODE');          // unionId: u001 (相同)
$user3 = Union::wechat()->app('APP_CODE');        // unionId: u001 (相同)
$user4 = Union::wechat()->h5('H5_CODE');          // unionId: u001 (相同)

// 业务侧只需用 unionId 关联业务账号
$businessUser = User::where('union_id', $user1->unionId)->first();
```

### 自定义适配器（业务扩展）

```php
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;
use Kode\MiniApp\Union\Channel;

class MyLoginAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatMini;
    }

    public function authenticate(array $payload): UnionUser
    {
        // 自定义登录逻辑
        return UnionUser::fromRaw(
            channel: Channel::WechatMini,
            openId:  $payload['openid'] ?? '',
            raw:     $payload,
        );
    }
}

$kernel->union()->registerLoginAdapter(new MyLoginAdapter());
```

### 底层 vs 统一入口

| 维度 | 底层 Provider / App | Union 统一入口 |
|------|--------------------|-----------------|
| 适用对象 | 框架开发者、扩展定制 | 业务开发者（**99% 场景**） |
| 能力粒度 | 30+ 模块细粒度 | 场景登录 + 4 大能力 + 透传 Provider |
| 学习成本 | 高（需了解每个模块） | 极低（几行代码搞定） |
| 跨平台切换 | 需重写业务 | 无缝切换 |
| UnionID 处理 | 手动 | 自动 |

> **设计哲学**：底层 Provider/App 是 SDK 的"零件库"，Union 是面向业务场景的"成品工具"。
> 业务侧 99% 的需求都可以用 Union 解决，仅在需要极细粒度控制时才用底层 Provider。

### 详细文档

每个平台都有独立的详细使用文档：

| 平台 | 文档路径 | 说明 |
|------|----------|------|
| **Union 统一入口** | [docs/union.md](docs/union.md) | 跨平台一键登录 / 支付 / 回调，跨端账号自动合并（**推荐阅读**） |
| 微信 | [docs/wechat.md](docs/wechat.md) | 公众号/小程序，30+ 功能模块 |

```php
$app = $kernel->wechat()->app();

// 小程序登录
$session = $app->auth()->session($code);

// 获取 AccessToken
$token = $app->auth()->token();

// JS-SDK 配置
$jssdk = $app->jssdk()->config($url, ['updateAppMessageShareData']);

// 用户管理
$users = $app->user()->list();
$userInfo = $app->user()->info($openid);
$app->user()->remark($openid, 'VIP用户');

// 素材管理
$media = $app->media()->upload('image', '/path/to/image.jpg');
$app->media()->uploadNews([['title' => '标题', 'content' => '内容']]);
$app->media()->delete($mediaId);

// 菜单管理
$app->menu()->create([
    ['type' => 'click', 'name' => '今日歌曲', 'key' => 'V1001_TODAY_MUSIC'],
    ['type' => 'view', 'name' => '搜索', 'url' => 'http://www.soso.com/'],
]);
$app->menu()->delete();

// 客服消息
$app->customerService()->text($openid, '您好！');
$app->customerService()->image($openid, $mediaId);
$app->customerService()->news($openid, [['title' => '标题', 'description' => '描述', 'url' => 'https://example.com', 'picurl' => 'https://example.com/pic.jpg']]);
$app->customerService()->miniProgramPage($openid, '标题', $appid, 'pages/index/index', $thumbMediaId);
$app->customerService()->menu($openid, '请选择', [['id' => '1', 'content' => '选项1']], '感谢使用');

// 客服管理
$kfList = $app->customerService()->list();
$records = $app->customerService()->msgRecord(strtotime('-1 day'), time());
$app->customerService()->invite($openid, 'kf_account@gh_xxx');

// 发送订阅消息（小程序）
$app->message()->sendSubscribe($openid, $templateId, ['thing1' => ['value' => '测试']]);

// 发送模板消息（公众号）
$app->message()->sendTemplate($openid, $templateId, '', ['thing1' => ['value' => '测试']]);

// 小程序码生成
$qrCode = $app->miniProgramCode()->getUnlimited(['scene' => 'id=123', 'page' => 'pages/index/index']);
file_put_contents('/tmp/qrcode.png', $qrCode);

// 数据分析
$retain = $app->dataAnalysis()->getDailyRetain('2024-01-01', '2024-01-07');
$trend  = $app->dataAnalysis()->getDailyVisitTrend('2024-01-01', '2024-01-07');
$portrait = $app->dataAnalysis()->getUserPortrait('2024-01-01', '2024-01-07');

// 订阅消息管理（小程序）
$app->subscribeMessage()->send($openid, $templateId, ['thing1' => ['value' => '测试']]);
$templates = $app->subscribeMessage()->getTemplateList();
$app->subscribeMessage()->deleteTemplate($priTmplId);

// 支付（2.0 起统一经 kode/pays；先登录拿到 UnionUser，openid 自动注入）
$user  = $kernel->union()->wechat()->mini($code);
$pay   = $kernel->union()->wechat()->pay();

// 统一下单
$order = $pay->createOrder([
    'description'  => '商品描述',
    'out_trade_no' => 'ORDER_001',
    'amount'       => ['total' => 100],
], $user);

// 查询订单
$pay->queryOrder('ORDER_001');

// 关闭订单
$pay->closeOrder('ORDER_001');

// 申请退款
$pay->refund([
    'out_trade_no'  => 'ORDER_001',
    'out_refund_no' => 'REFUND_001',
    'reason'        => '用户申请退款',
    'amount'        => [
        'refund'   => 100,
        'total'    => 100,
        'currency' => 'CNY',
    ],
]);

// 查询退款
$pay->queryRefund('REFUND_001');

// 账单 / 分账 / 转账等高级能力由 kode/pays 网关直接提供，详见 kode/pays 文档

// 小程序订单物流同步（发货）
// 标准快递发货
$app->shipping()->express(
    'ORDER_001',
    '1',
    [
        [
            'tracking_no'   => 'SF1234567890',
            'express_company' => '顺丰速运',
            'item_desc'     => '商品描述',
        ],
    ],
    $payerOpenid
);

// 无需物流发货（虚拟商品/服务）
$app->shipping()->noShipping('ORDER_001', '1', $payerOpenid);

// 同城配送发货
$app->shipping()->sameCity('ORDER_001', '1', [
    ['tracking_no' => 'RIDER_001', 'express_company' => '同城配送', 'item_desc' => '商品描述'],
], $payerOpenid);

// 用户自提发货
$app->shipping()->selfPickup('ORDER_001', '1', [
    ['tracking_no' => 'PICKUP_001', 'express_company' => '用户自提', 'item_desc' => '商品描述'],
], $payerOpenid);

// 查询发货信息
$app->shipping()->getOrder('', 'ORDER_001');

// 确认收货提醒
$app->shipping()->notifyConfirmReceive('', 'ORDER_001');

// 设置消息跳转路径
$app->shipping()->setMsgJumpPath('pages/order/detail');

// 内容安全检测
$result = $app->security()->msgSecCheck('待检测文本内容');
$result = $app->security()->imgSecCheck('https://example.com/image.jpg');
$result = $app->security()->mediaCheckAsync('https://example.com/audio.mp3', 1);

// URL Scheme / URL Link（短信、邮件、微信外打开小程序）
$scheme = $app->urlLink()->generateScheme([
    'jump_wxa' => ['path' => '/pages/index/index', 'query' => 'id=123'],
]);
$link = $app->urlLink()->generateUrlLink([
    'path'  => '/pages/index/index',
    'query' => 'id=123',
]);
$shortLink = $app->urlLink()->generateShortLink('pages/index/index?id=123', '页面标题');

// 插件管理
$app->plugin()->applyPlugin('wx1234567890');
$plugins = $app->plugin()->list();
$app->plugin()->unbindPlugin('wx1234567890');

// 小程序直播
$app->live()->createRoom([
    'name'         => '直播间名称',
    'coverImg'     => 'https://example.com/cover.jpg',
    'startTime'    => time(),
    'endTime'      => time() + 7200,
    'anchorName'   => '主播名称',
    'anchorWechat' => 'anchor_wechat',
    'type'         => 1,
]);
$liveRooms = $app->live()->getLiveInfo();
$replay = $app->live()->getReplay($roomId);
$app->live()->addGoods($goodsInfo);
$app->live()->audit($goodsId);

// 附近小程序
$app->nearby()->addPoi(['related_name' => '门店名称', 'related_credential' => '营业执照号', 'related_address' => '地址', 'related_phone' => '电话']);
$app->nearby()->listPoi();
$app->nearby()->deletePoi($poiId);
$app->nearby()->setStatus($poiId, 1);

// 门店小程序
$app->store()->create(['name' => '门店名称', 'longitude' => '113.2644', 'latitude' => '23.1291', 'address' => '广州市天河区', 'phone' => '020-12345678']);
$app->store()->list();
$app->store()->get($poiId);
$app->store()->update(['poi_id' => $poiId, 'name' => '新门店名称']);
$app->store()->delete($poiId);

// 卡券
$app->card()->create(['card_type' => 'GROUPON', 'groupon' => ['base_info' => ['brand_name' => '商家名称'], 'deal_detail' => '优惠详情']]);
$app->card()->get($cardId);
$app->card()->consume('CODE123', $cardId);
$app->card()->list();

// 摇一摇
$app->shake()->applyDeviceId(10);
$app->shake()->addPage(['title' => '页面标题', 'description' => '描述', 'page_url' => 'https://example.com', 'comment' => '备注']);
$app->shake()->getShakeInfo($ticket);

// 发票
$app->invoice()->getAuthUrl(['s_pappid' => 'wx123', 'order_id' => 'ORDER001', 'money' => 100, 'timestamp' => time(), 'source' => 'web']);
$app->invoice()->makeOutInvoice(['wxopenid' => $openid, 'order_id' => 'ORDER001', 'card_id' => $cardId, 'card_ext' => '{}']);
$app->invoice()->queryInvoiceInfo($cardId, $encryptCode);

// 连Wi-Fi
$app->wifi()->addDevice(['shop_id' => 123, 'ssid' => 'MyWiFi', 'password' => 'password123']);
$app->wifi()->deviceList();
$app->wifi()->getQrcode(123);

// 微信小店（视频号电商）
$app->goods()->add(['title' => '商品标题', 'head_imgs' => ['https://example.com/img.jpg'], 'category_id' => 100]);
$app->goods()->list();
$app->goods()->get($productId);
$app->goods()->listing($productId);
$app->goods()->orderList();

// 红包
$app->redpack()->send(['send_name' => '商家名称', 're_openid' => $openid, 'total_amount' => 100, 'total_num' => 1, 'wishing' => '恭喜发财', 'act_name' => '活动名称', 'remark' => '备注']);
$app->redpack()->query('MCHBILLNO001');

// 广告
$app->ad()->createAdUnit(['ad_unit_name' => '广告单元1', 'ad_unit_type' => 1]);
$app->ad()->adUnitList();
$app->ad()->getData($adUnitId, '2024-01-01', '2024-01-31');

// 即时配送
$app->express()->deliveryList();
$app->express()->addOrder(['shopid' => 'SHOP001', 'shop_order_id' => 'ORDER001', 'shop_no' => '001', 'delivery_id' => 1, 'openid' => $openid, 'sender' => [], 'receiver' => [], 'cargo' => [], 'order_info' => []]);
$app->express()->cancelOrder(['shopid' => 'SHOP001', 'shop_order_id' => 'ORDER001', 'delivery_id' => 1, 'waybill_id' => 'WB001']);

// 搜一搜
$app->search()->submitPages(['pages/index/index', 'pages/detail/detail']);
$app->search()->getData('2024-01-01', '2024-01-31');

// 动态消息
$app->dynamicMessage()->createActivityId();
$app->dynamicMessage()->setUpdatableMsg(['activity_id' => $activityId, 'target_state' => 0, 'template_info' => ['parameter_list' => []]]);

// 设备功能
$app->device()->getQrcode([['id' => 'DEVICE001', 'mac' => '00:11:22:33:44:55', 'connect_protocol' => '3', 'auth_key' => '', 'close_strategy' => '1', 'conn_strategy' => '1', 'crypt_method' => '0', 'auth_ver' => '0', 'manu_mac_pos' => '-1', 'ser_mac_pos' => '-2', 'ble_simple_protocol' => '0']]);
$app->device()->authorize([['id' => 'DEVICE001', 'mac' => '00:11:22:33:44:55', 'connect_protocol' => '3', 'auth_key' => '', 'close_strategy' => '1', 'conn_strategy' => '1', 'crypt_method' => '0', 'auth_ver' => '0', 'manu_mac_pos' => '-1', 'ser_mac_pos' => '-2', 'ble_simple_protocol' => '0']]);
$app->device()->bind($ticket, $deviceId, $openid);
$app->device()->getStat($deviceId);

// 云开发
$app->cloudbase()->invokeFunction('myFunction', ['key' => 'value']);
$app->cloudbase()->databaseQuery(['env' => 'prod-env', 'query' => 'db.collection("users").get()']);
$app->cloudbase()->databaseAdd(['env' => 'prod-env', 'query' => 'db.collection("users").add({data:{name:"张三"}})']);
$app->cloudbase()->uploadFile('/path/to/file.jpg');

// 支付（2.0 起统一经 kode/pays，先登录拿到 UnionUser，openid 自动注入）
$user  = $kernel->union()->wechat()->mini($code);
$order = $kernel->union()->wechat()->pay()->createOrder([
    'out_trade_no' => 'ORDER_001',
    'description'  => '商品',
    'amount'       => ['total' => 100],
], $user);
```

### 微信服务端消息处理

```php
use Kode\MiniApp\Server\Message;

$server = $kernel->wechat()->app()->server();

$server->on('text', function (array $payload, $app) {
    return Message::toXml(Message::text('收到：' . $payload['Content'], $payload));
});

$server->on('subscribe', function (array $payload, $app) {
    return Message::toXml(Message::text('感谢关注！', $payload));
});

$response = $server->serve();
$response->send();
```

### 微信/支付宝支付回调通知

```php
$notify = $kernel->wechat()->app()->notify();

$result = $notify
    ->onPaid(function (array $payload, $app) {
        // 处理支付成功逻辑
        $outTradeNo = $payload['out_trade_no'];
        // 更新订单状态...
    })
    ->onRefund(function (array $payload, $app) {
        // 处理退款通知
    })
    ->handle();

// 返回给微信/支付宝
if ($result['code'] === 'SUCCESS') {
    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code></xml>';
} else {
    echo '<xml><return_code><![CDATA[FAIL]]></return_code></xml>';
}
```

### 统一支付回调（Union 入口）

除各 Provider 自带 `notify()`（含签名验签）外，Union 还提供跨端统一的回调
**归一化**入口，适合「一个回调控制器按渠道分发」的场景，与统一登录 / 支付 / 解密入口对称：

```php
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\Channel;

$union = $kernel->union();

// 按渠道取通知适配器：微信(小程序/公众号/PC/App/开放平台) / 企业微信 / 支付宝 / 抖音 / 百度 / QQ 均已支持
$notify = $union->wechat()->notify();   // 或 alipay() / baidu() / douyin() / qq() / wechatWork()

// $raw 为业务侧已将 XML / 表单参数解析后的关联数组
$payload = $notify->decode($raw);

// 归一化字段随渠道不同：
//   微信/QQ：out_trade_no, transaction_id, total_fee, openid, result_code, raw
//   支付宝  ：out_trade_no, trade_no, total_amount, trade_status, raw
//   抖音/百度：out_trade_no, trade_no, result_code|status, raw
//   企业微信：event_type, raw
$outTradeNo = $payload['out_trade_no'];
```

> 注意：`Union::notify()->decode()` 仅做字段归一化，**不验签**。微信 / 支付宝 / 抖音 / 百度 /
> 企业微信的回调签名验签请使用各 Provider 自带的 `notify()`（上文 Provider 级用法）；
> QQ 回调由 `Qq\Modules\Notify` 内部完成 XML+MD5 验签，建议直接用 `$kernel->qq()->app()->notify()`。
> 业务侧务必在 `decode()` 之前完成签名校验，避免伪造回调。

### 微信企业号

```php
$app = $kernel->wechatWork()->app();

// 获取用户信息
$user = $app->auth()->user($code);
$userDetail = $app->auth()->userDetail($userId);

// 通讯录管理
$app->contact()->createUser(['userid' => 'zhangsan', 'name' => '张三']);
$departments = $app->contact()->departments();
$users = $app->contact()->departmentUsers(1);

// 标签管理
$app->tag()->create('新员工');
$app->tag()->addUsers(1, ['zhangsan']);
$tags = $app->tag()->list();

// 部门管理
$app->department()->create(['name' => '技术部', 'parentid' => 1]);
$departments = $app->department()->list();
$app->department()->update(['id' => 2, 'name' => '产品部']);
$app->department()->delete(2);

// 客户联系（外部联系人）
$followUsers = $app->externalContact()->getFollowUserList();
$customers = $app->externalContact()->list('zhangsan');
$detail = $app->externalContact()->get($externalUserid);
$app->externalContact()->addContactWay([
    'type' => 1,
    'scene' => 1,
    'style' => 1,
    'remark' => '渠道客户',
]);
$app->externalContact()->remark([
    'userid' => 'zhangsan',
    'external_userid' => $externalUserid,
    'remark' => 'VIP客户',
]);

// 客户群管理
$groups = $app->externalContact()->groupChatList();
$groupDetail = $app->externalContact()->groupChatGet('chat_id');

// 离职继承
$unassigned = $app->externalContact()->getUnassignedList();
$app->externalContact()->transfer([
    'external_userid' => $externalUserid,
    'handover_userid' => 'zhangsan',
    'takeover_userid' => 'lisi',
]);

// 客户标签
$tags = $app->externalContact()->getCorpTagList();
$app->externalContact()->addCorpTag([
    'group_name' => '客户等级',
    'tag' => [['name' => 'VIP']],
]);

// 素材管理
$media = $app->media()->upload('image', '/path/to/image.jpg');
$app->media()->uploadImg('/path/to/image.jpg');
$app->media()->uploadAttachment('image', '/path/to/image.jpg', 'image');

// 应用管理
$app->agent()->get(1000002);
$app->agent()->list();
$app->agent()->set(['agentid' => 1000002, 'report_location_flag' => 0]);

// 消息推送
$app->message()->text('Hello World', ['zhangsan']);
$app->message()->markdown('# 标题\n内容', ['zhangsan']);
$app->message()->news([['title' => '标题', 'description' => '描述', 'url' => 'https://example.com', 'picurl' => 'https://example.com/pic.jpg']], ['zhangsan']);
$app->message()->file($mediaId, ['zhangsan']);
$app->message()->image($mediaId, ['zhangsan']);
$app->message()->voice($mediaId, ['zhangsan']);
$app->message()->video($mediaId, '标题', '描述', ['zhangsan']);
$app->message()->textCard('标题', '描述内容', 'https://example.com', ['zhangsan'], '查看详情');
$app->message()->miniProgramNotice([
    'appid'             => 'wx123',
    'page'              => 'pages/index',
    'title'             => '通知标题',
    'description'       => '通知内容',
    'emphasis_first_item' => true,
    'content_item'      => [['key' => '订单号', 'value' => '123456']],
], ['zhangsan']);

// 审批
$app->approval()->template($templateId);
$app->approval()->apply($approvalData);
$app->approval()->detail($spNo);

// OA 打卡汇报
$app->oa()->getCheckinOption(time(), ['zhangsan']);
$app->oa()->getCheckinData(strtotime('-7 days'), time(), ['zhangsan']);
$app->oa()->getCheckinDayData(strtotime('-7 days'), time(), ['zhangsan']);
$app->oa()->getCheckinMonthData(strtotime('-30 days'), time(), ['zhangsan']);
$app->oa()->getJournalRecordList(strtotime('-7 days'), time());
$app->oa()->getJournalStat(strtotime('-7 days'), time());

// 会议室管理
$app->meeting()->create(['name' => '会议室A', 'capacity' => 10, 'city' => '深圳']);
$app->meeting()->list();
$app->meeting()->getBookingInfo($meetingRoomId, '2024-01-01T09:00:00', '2024-01-01T18:00:00');
$app->meeting()->book(['meetingroom_id' => $meetingRoomId, 'subject' => '周会', 'start_time' => time(), 'end_time' => time() + 3600, 'booker' => 'zhangsan']);
$app->meeting()->cancelBook($meetingId);

// 公费电话
$app->dial()->call(['zhangsan'], 'lisi', '拨打原因');
$app->dial()->records(strtotime('-7 days'), time());

// 日程管理
$app->schedule()->add(['organizer' => 'zhangsan', 'start_time' => time(), 'end_time' => time() + 3600, 'attendees' => [['userid' => 'lisi']], 'summary' => '周会', 'description' => '讨论下周计划']);
$app->schedule()->get($scheduleId);
$app->schedule()->update(['schedule_id' => $scheduleId, 'summary' => '更新后的标题']);
$app->schedule()->delete($scheduleId);

// 收集表
$app->collect()->create(['form_title' => '入职信息收集', 'form_desc' => '请填写个人信息', 'form_question' => [['question_id' => 1, 'title' => '姓名', 'question_type' => 'text']]]);
$app->collect()->get($formid);
$app->collect()->getAnswer($formid);

// 微盘
$app->drive()->spaceCreate(['space_name' => '项目资料', 'auth_list' => ['userid' => 'zhangsan', 'auth' => 1]]);
$app->drive()->spaceInfo($spaceId);
$app->drive()->fileList($spaceId);
$app->drive()->fileDownload($spaceId, $fileId);

// 上下游/互联企业
$app->corpGroup()->getAppShareInfo();
$app->corpGroup()->unionidToExternalUserid($unionid, $openid);

// 会话内容存档
$app->msghub()->getPermitUserList();
$app->msghub()->getSingleAgreeStatus(['zhangsan', 'lisi']);
$app->msghub()->getRoomAgreeStatus(['ROOM001']);
$app->msghub()->getRoomInfo('ROOM001');

// 服务端消息处理
$server = $app->server();
$server->on('text', fn($payload) => 'success');
$server->serve()->send();
```

### 钉钉

```php
$app = $kernel->dingtalk()->app();

// 获取用户信息
$user = $app->auth()->user($code);
$detail = $app->auth()->userDetail($userId);

// 通讯录
$app->contact()->createUser(['userid' => 'zhangsan', 'name' => '张三']);
$departments = $app->contact()->departments();

// 消息
$app->message()->text('Hello', ['zhangsan']);
$app->message()->markdown('标题', '内容', ['zhangsan']);
$app->message()->image($mediaId, ['zhangsan']);
$app->message()->file($mediaId, ['zhangsan']);
$app->message()->link('标题', '内容', 'https://example.com', 'https://example.com/pic.jpg', ['zhangsan']);
$app->message()->oa($oaContent, ['zhangsan']);
$app->message()->actionCard([
    'title'          => '标题',
    'markdown'       => '内容',
    'single_title'   => '查看详情',
    'single_url'     => 'https://example.com',
], ['zhangsan']);

// 审批
$app->approval()->instance($processInstanceId);
$app->approval()->create($data);

// 群机器人消息
$app->robot()->text($webhook, $secret, 'Hello 钉钉');
$app->robot()->markdown($webhook, $secret, '标题', '**加粗内容**');
$app->robot()->link($webhook, $secret, '标题', '内容', 'https://example.com');
$app->robot()->actionCard($webhook, $secret, [
    'title' => '标题',
    'markdown' => '内容',
    'singleTitle' => '查看详情',
    'singleURL' => 'https://example.com',
]);

// 考勤管理
$app->attendance()->list('2024-01-01 00:00:00', '2024-01-31 23:59:59', ['zhangsan']);
$app->attendance()->listSchedule(['zhangsan'], '2024-01-01');
$app->attendance()->getGroup(1);
$app->attendance()->getRecord('2024-01-01 00:00:00', '2024-01-31 23:59:59', ['zhangsan']);

// 智能人事
$app->hrm()->getEmpRosterDetail(['zhangsan']);
$app->hrm()->queryOnJob();
$app->hrm()->queryPreEntry();
$app->hrm()->queryDimission();

// 日志管理
$app->report()->list('2024-01-01 00:00:00', '2024-01-31 23:59:59');
$app->report()->get($reportId);
$app->report()->templateList();

// 项目管理
$app->project()->create(['name' => '新项目', 'manager_uid' => 'zhangsan', 'description' => '项目描述']);
$app->project()->get($projectId);
$app->project()->list();
$app->project()->addTask($projectId, ['content' => '完成需求分析', 'executor_uid' => 'lisi']);
$app->project()->taskList($projectId);

// 智能工作流
$app->workflow()->createInstance(['process_code' => 'PROC-XXX', 'originator_user_id' => 'zhangsan', 'dept_id' => 1, 'form_component_values' => [['name' => '标题', 'value' => '请假申请']]]);
$app->workflow()->getInstance($processInstanceId);
$app->workflow()->templateList();
$app->workflow()->instanceList(['process_code' => 'PROC-XXX', 'start_time' => strtotime('-7 days') * 1000]);
$app->workflow()->terminateInstance($processInstanceId);
```

### 飞书

```php
$app = $kernel->lark()->app();

// 获取用户信息
$user = $app->auth()->user($code);
$detail = $app->auth()->userDetail($userId);

// 通讯录
$departments = $app->contact()->departments();
$users = $app->contact()->departmentUsers('0');

// 消息
$app->message()->text('ou_xxx', 'Hello World');
$app->message()->post('ou_xxx', ['zh_cn' => ['title' => '标题', 'content' => [['tag' => 'text', 'text' => '内容']]]]);
$app->message()->image('ou_xxx', $imageKey);
$app->message()->file('ou_xxx', $fileKey);
$app->message()->interactive('ou_xxx', ['config' => ['wide_screen_mode' => true], 'elements' => []]);

// 审批
$app->approval()->create($data);
$app->approval()->instance($instanceCode);

// 多维表格
$app->bitable()->meta($appToken);
$app->bitable()->tables($appToken);
$app->bitable()->createRecord($appToken, $tableId, ['字段名' => '值']);
$app->bitable()->records($appToken, $tableId);
$app->bitable()->updateRecord($appToken, $tableId, $recordId, ['字段名' => '新值']);
$app->bitable()->deleteRecord($appToken, $tableId, $recordId);

// 文档管理
$doc = $app->doc()->create('新文档标题', $folderToken);
$app->doc()->meta($documentId);
$app->doc()->rawContent($documentId);
$app->doc()->blocks($documentId, $blockId);
$app->doc()->createBlock($documentId, $blockId, [
    ['block_type' => 2, 'text' => ['elements' => [['text_run' => ['content' => 'Hello World']]]]],
]);

// 日历管理
$app->calendar()->create(['summary' => '团队日历', 'description' => '用于团队协作']);
$app->calendar()->list();
$app->calendar()->get($calendarId);
$app->calendar()->delete($calendarId);
$app->calendar()->createEvent($calendarId, [
    'summary'     => '周会',
    'start'       => ['date_time' => '2024-01-01T10:00:00+08:00'],
    'end'         => ['date_time' => '2024-01-01T11:00:00+08:00'],
    'attendees'   => [['user_id' => 'ou_xxx']],
]);
$app->calendar()->listEvents($calendarId);
$app->calendar()->getEvent($calendarId, $eventId);
$app->calendar()->deleteEvent($calendarId, $eventId);

// 任务管理
$task = $app->task()->create(['summary' => '完成需求文档', 'due' => ['date' => '2024-01-15']]);
$app->task()->get($taskGuid);
$app->task()->update($taskGuid, ['summary' => '更新后的任务标题']);
$app->task()->complete($taskGuid);
$app->task()->uncomplete($taskGuid);
$app->task()->delete($taskGuid);

// 知识库
$app->wiki()->list();
$app->wiki()->get($spaceId);
$app->wiki()->nodes($spaceId);
$app->wiki()->createNode($spaceId, ['obj_type' => 22, 'node_type' => 'origin', 'origin_node_token' => $docToken, 'parent_node_token' => $parentToken, 'title' => '新节点']);

// 审批定义（流程配置）
$app->approvalDef()->list();
$app->approvalDef()->get($approvalCode);
$app->approvalDef()->createInstance(['approval_code' => $approvalCode, 'user_id' => 'ou_xxx', 'form' => ['控件ID' => ['value' => '值']]]);
$app->approvalDef()->instanceList($approvalCode);
$app->approvalDef()->approve(['instance_code' => $instanceCode, 'user_id' => 'ou_xxx', 'comment' => '同意']);
$app->approvalDef()->reject(['instance_code' => $instanceCode, 'user_id' => 'ou_xxx', 'comment' => '驳回']);

// 邮件
$app->mail()->send(['subject' => '会议通知', 'body' => ['content' => '明天上午10点开会', 'content_type' => 'text/plain'], 'to' => [['mail_address' => 'user@example.com']]]);
$app->mail()->mailGroupList();
$app->mail()->createMailGroup(['mail_group_name' => '技术部', 'email' => 'tech@company.com']);
$app->mail()->getMailGroup($mailGroupId);
$app->mail()->deleteMailGroup($mailGroupId);
```

### 支付宝

```php
$app = $kernel->alipay()->app();

// 登录
$user = $app->auth()->token($code);
$userInfo = $app->auth()->user($accessToken);

// 支付（2.0 起统一经 kode/pays，先登录再下单，buyer_id 自动注入）
$app  = $kernel->alipay()->app();
$user = $app->auth()->token($code);
$pay  = $kernel->union()->alipay()->pay();

$order = $pay->createOrder([
    'out_trade_no' => 'ORDER_001',
    'total_amount' => '99.99',
    'subject'      => '测试商品',
], $user);

// 退款 / 查询 / 对账等能力由 kode/pays 网关提供（composer require kode/pays 后可用）

// 转账
$app->transfer()->create([
    'out_biz_no'    => 'TRANSFER_001',
    'trans_amount'  => '100.00',
    'order_title'   => '提现',
    'payee_account' => 'user@example.com',
    'payee_name'    => '张三',
]);

// 账单
$app->bill()->download('trade', '2024-01-01');

// 营销
$app->marketing()->createCashActivity(['coupon_name' => '现金红包活动', 'prize_type' => 'FIX', 'total_money' => 10000, 'total_num' => 100]);
$app->marketing()->triggerCash(['user_id' => $userId, 'out_biz_no' => 'BIZ001', 'amount' => 100]);
$app->marketing()->createVoucherTemplate(['voucher_name' => '优惠券模板', 'brand_name' => '商家名称']);
$app->marketing()->sendVoucher(['voucher_template_id' => $templateId, 'user_id' => $userId]);
$app->marketing()->precreate(['out_trade_no' => 'ORDER001', 'total_amount' => '100.00', 'subject' => '商品标题']);
$app->marketing()->refund(['out_trade_no' => 'ORDER001', 'refund_amount' => '50.00']);

// 会员
$app->member()->info($accessToken);
$app->member()->authInfo($authToken);
$app->member()->pointBalance($userId);

// 回调通知
$result = $app->notify()
    ->onPaid(function ($payload) {
        $outTradeNo = $payload['out_trade_no'];
        // 更新订单...
    })
    ->handle();

echo 'success'; // 返回给支付宝
```

### 抖音

```php
$app = $kernel->douyin()->app();

// 登录
$user = $app->auth()->user($code);

// 视频管理
$app->video()->upload($accessToken, ['open_id' => $openId, 'video' => $videoData]);
$app->video()->create($accessToken, ['open_id' => $openId, 'item_id' => $itemId, 'title' => '视频标题', 'cover' => $coverUrl]);
$app->video()->list($accessToken, $openId);
$app->video()->data($accessToken, $openId, [$itemId]);
$app->video()->commentList($accessToken, $openId, $itemId);
$app->video()->commentReply($accessToken, ['open_id' => $openId, 'item_id' => $itemId, 'comment_id' => $commentId, 'content' => '回复内容']);
```

### 百度

```php
$app = $kernel->baidu()->app();

// 登录
$app->auth()->session($code);
$app->auth()->userInfo($accessToken);

// 支付（2.0 起统一经 kode/pays）
$user = $app->auth()->session($code);
$order = $kernel->union()->baidu()->pay()->createOrder([
    'dealId'     => 'DEAL001',
    'appKey'     => 'APP001',
    'totalAmount' => '100',
    'tpOrderId'  => 'ORDER001',
], $user);

// 模板消息
$app->message()->send(['touser' => $openId, 'template_id' => $templateId, 'data' => ['keyword1' => '值1', 'keyword2' => '值2']]);
$app->message()->templateList();
$app->message()->templateDetail($templateId);
$app->message()->deleteTemplate($templateId);
```

### QQ

```php
$app = $kernel->qq()->app();

// 登录
$user = $app->auth()->user($code);

// 支付（2.0 起统一经 kode/pays）
$pay = $kernel->union()->qq()->pay();
$order = $pay->createOrder([
    'body'            => '商品描述',
    'out_trade_no'    => 'ORDER001',
    'total_fee'       => 100,
    'spbill_create_ip'=> '127.0.0.1',
    'notify_url'      => 'https://example.com/notify',
    'trade_type'      => 'MINIAPP',
], $user);
$pay->queryOrder('ORDER001');
$pay->closeOrder('ORDER001');
$pay->refund(['out_trade_no' => 'ORDER001', 'out_refund_no' => 'REFUND001', 'total_fee' => 100, 'refund_fee' => 50]);
```

## Kode 生态桥接使用

### 支付（kode/pays，2.0 起为唯一支付路径）

2.0 起支付能力**完全由 kode/pays 承载**，安装本包即需 `composer require kode/pays`（硬依赖）：

```php
use Kode\MiniApp\Union\Union;

// 业务侧推荐用法：先登录拿 UnionUser，再经 Union 下单（openid / buyer_id 自动注入）
$user  = Union::wechat()->mini($code);
$order = Union::wechat()->pay()->createOrder([
    'out_trade_no' => 'ORDER_001',
    'description'  => '商品',
    'amount'       => ['total' => 100],
], $user);

// 回调验签 + 解密（委托 kode/pays）
$data = Union::wechat()->notify()->decode($payload, $headers);
```

桥接内部类 `Kode\MiniApp\Union\Bridge\PaysBridge` 提供诊断与自定义 resolver（`available()` /
`adapter()` / `notifyAdapter()` 等）；未安装 kode/pays 时 `Union::pay()` / `Union::notify()`
会直接抛出清晰异常，引导先 `composer require kode/pays`。

### 工具桥接（kode/tools）

```php
use Kode\MiniApp\Bridge\ToolsBridge;

// 自动检测并优先使用 kode/tools
$strClass = ToolsBridge::str();   // Kode\Tools\Str 或 Kode\MiniApp\Utils\Str
$signClass = ToolsBridge::sign(); // Kode\Tools\Sign 或 Kode\MiniApp\Utils\Sign

// 获取加密工具（仅 kode/tools 提供）
if (ToolsBridge::crypto()) {
    $crypto = ToolsBridge::crypto();
    // 使用加密工具...
}

// 获取二维码工具（仅 kode/tools 提供）
if (ToolsBridge::qrcode()) {
    $qrcode = ToolsBridge::qrcode();
    // 生成二维码...
}
```

### 异常桥接（kode/exception）

```php
use Kode\MiniApp\Bridge\ExceptionBridge;
use Kode\MiniApp\Contracts\Platform;

// 自动使用 kode/exception 的异常码体系（如已安装）
$exception = ExceptionBridge::wrap(
    '请求失败',
    Platform::Wechat,
    1001,
    $previous
);

// 异常码映射规则
// 微信: 100000+, 支付宝: 200000+, 抖音: 300000+
// 百度: 400000+, QQ: 500000+, 企业微信: 600000+
// 钉钉: 700000+, 飞书: 800000+
```

## HTTP 客户端

SDK 内置基于 Guzzle 的 HTTP 客户端，支持中间件、重试、日志：

```php
use Kode\MiniApp\Core\HttpClient;
use Monolog\Logger;

$logger = new Logger('miniapp');
$http = new HttpClient(['timeout' => 30], $logger);

$kernel = new Kernel($config, $http);
```

## 缓存

SDK 推荐直接使用 **SessionManager** 进行会话/约束管理（见上文），底层基于 PSR-16 Cache 接口，
可注入任何实现（Redis / Memcached / 文件 等）：

```php
use Kode\MiniApp\Session\SessionManager;
use Kode\MiniApp\Session\CacheSessionStorage;
use Kode\Cache\Psr16\RedisCache;  // 或其他 PSR-16 Cache 实现

$session = new SessionManager(
    new CacheSessionStorage(new RedisCache($redis))
);
```

> **历史类说明**：`Kode\MiniApp\Core\Cache` 和 `Kode\MiniApp\Core\AccessToken` 是早期为手动管理
> AccessToken 缓存提供的工具类。SDK 内部已不再使用，业务侧推荐使用 `kode/cache` + SessionManager
> 的组合。如确有需要，可继续使用这两个类（保持向后兼容）。

## 工具类

```php
use Kode\MiniApp\Utils\Str;
use Kode\MiniApp\Utils\Sign;
use Kode\MiniApp\Utils\Xml;

// 字符串
Str::random(16);
Str::uuid();
Str::camel('foo_bar'); // fooBar
Str::snake('fooBar');  // foo_bar

// 签名
Sign::md5($params, $key);
Sign::hmac($params, $key);
Sign::rsa($params, $privateKey);
Sign::verifyRsa($params, $publicKey, $sign);

// XML
Xml::build(['name' => 'test']);
Xml::parse($xmlString);
```

## 异常处理

```php
use Kode\MiniApp\Exceptions\MiniAppException;
use Kode\MiniApp\Exceptions\PlatformException;
use Kode\MiniApp\Exceptions\ConfigException;

try {
    $kernel->wechat()->app()->auth()->session($code);
} catch (ConfigException $e) {
    // 配置错误
} catch (PlatformException $e) {
    // 平台级错误，包含平台信息
    echo $e->platform()->label();
} catch (MiniAppException $e) {
    // 通用错误
}
```

## 版本管理

版本号由 `composer.json` 统一管理：

```bash
# 升级 patch 版本（1.1.0 -> 1.1.1）
composer run version:bump

# 升级 minor 版本
composer run version:bump minor

# 升级 major 版本
composer run version:bump major

# 打 tag 并推送
composer run version:tag
```

## 代码质量

```bash
# 运行全部检查
composer run check

# 单独运行
composer run phpstan
composer run cs
composer run test
```

## 扩展新平台

### 扩展 Union 平台聚合（业务侧主入口）

1. `src/Union/Platforms/` 下创建新的 `XxxUnion.php` 继承 `PlatformUnion`
2. `src/Union/Union.php` 的 `PLATFORM_MAP` 中添加映射
3. `tests/Union/PlatformUnionTest.php` 编写测试

```php
// 示例：新增"小红书"平台聚合
namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

final class XiaohongshuUnion extends PlatformUnion
{
    public function platform(): string
    {
        return 'xiaohongshu';
    }

    public function mini(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mini');
    }

    protected function sceneMap(): array
    {
        return [
            'mini' => Channel::XiaohongshuMini,  // 需先在 Channel.php 添加枚举
        ];
    }

    protected function defaultChannel(): Channel
    {
        return Channel::XiaohongshuMini;
    }

    protected function defaultPayChannel(): Channel
    {
        return Channel::XiaohongshuMini;
    }
}
```

```php
// src/Union/Union.php 的 PLATFORM_MAP 中添加
private const PLATFORM_MAP = [
    // ...
    'xiaohongshu' => ['xiaohongshu', XiaohongshuUnion::class],
];
```

业务侧立即可用：

```php
$user = Union::xiaohongshu()->mini('CODE');
$order = Union::xiaohongshu()->pay()->createOrder([...]);
```

### 扩展 Provider（底层细粒度接口）

1. `src/Contracts/Platform.php` 添加枚举值
2. `src/Providers/{Platform}/` 实现 Provider、App、Config、Modules
3. `src/Kernel.php` 注册 Provider
4. `tests/Providers/{Platform}/` 编写测试

### 扩展 Union 适配器（底层协议适配）

1. `src/Union/Channel.php` 添加渠道枚举
2. `src/Union/Channels/{Platform}/` 实现 LoginAdapter / UserAdapter / PayAdapter / NotifyAdapter
3. `src/Union/Union.php` 的 `build*Adapter()` match 中添加渠道路由
4. `tests/Union/` 编写测试

```php
// 示例：实现一个自定义登录适配器
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

class MyLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatWork;  // 也可新增自定义渠道
    }

    public function authenticate(array $payload): UnionUser
    {
        // 自定义登录逻辑
        return UnionUser::fromRaw(
            channel: Channel::WechatWork,
            openId:  $payload['openid'] ?? '',
            raw:     $payload,
        );
    }
}

$kernel->union()->registerLoginAdapter(new MyLoginAdapter($kernel));
```

## 许可证

Apache-2.0 License
