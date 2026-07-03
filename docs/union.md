# 统一入口 (Union) - 跨平台一键登录 / 支付 / 回调

> Kode MiniApp SDK 的统一入口，屏蔽各平台差异，业务侧只需关心"业务场景"。

## 目录

- [设计理念](#设计理念)
- [快速开始](#快速开始)
- [支持的渠道 (Channel)](#支持的渠道-channel)
- [统一用户模型 (UnionUser)](#统一用户模型-unionuser)
- [登录认证](#登录认证)
- [用户资料](#用户资料)
- [支付下单](#支付下单)
- [回调通知](#回调通知)
- [统一账号体系 (UnionID)](#统一账号体系-unionid)
- [自定义适配器](#自定义适配器)
- [底层 vs 统一入口](#底层-vs-统一入口)

## 设计理念

```
┌─────────────────────────────────────────────┐
│ 业务侧代码                                    │
│  $user = $kernel->union()->authenticate(   │
│      Channel::WechatMini, ['code' => 'xxx']  │
│  );                                          │
└──────────────────────┬──────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────┐
│ Union 统一入口                                │
│  - Channel 枚举路由                           │
│  - Adapter 适配器解析                         │
│  - 统一 UnionUser 构造                       │
└──────────────────────┬──────────────────────┘
                       │
        ┌──────────────┼──────────────┐
        ▼              ▼              ▼
   微信 Adapter    支付宝 Adapter   抖音 Adapter
        │              │              │
        └──────┬───────┴──────┬───────┘
               ▼              ▼
           Provider / App / Module (底层实现)
```

**核心价值**：
- 业务侧只 `use Union` 一个命名空间
- 跨平台数据格式统一（UnionUser）
- UnionID 跨平台识别同一用户
- 底层 Provider/App/Module 仍可单独使用

## 快速开始

```php
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Union\Channel;

$kernel = new Kernel([
    'wechat' => [
        'app_id'     => 'wxapp0000000000',
        'app_secret' => 'app-secret',
    ],
    'wechat_open' => [
        'component_appid'  => 'wxcomp0000000000',
        'component_secret' => 'comp-secret',
        'token'            => 'token',
        'encoding_aes_key' => str_repeat('a', 43),
    ],
    'qq' => [
        'app_id'     => 'qqapp0000000000',
        'app_secret' => 'qq-secret',
    ],
]);

// 一键登录 - 业务侧完全无感
$user = $kernel->union()->authenticate(
    Channel::WechatMini,
    ['code' => 'JS_CODE']
);

echo $user->unionId;   // 跨平台统一 ID
echo $user->openId;    // 平台内 OpenID
echo $user->channel;   // 渠道
echo $user->nickname;  // 标准化昵称
```

## 支持的渠道 (Channel)

```php
enum Channel: string
{
    // 微信生态
    case WechatMp        = 'wechat_mp';       // 公众号
    case WechatMini      = 'wechat_mini';     // 小程序
    case WechatH5        = 'wechat_h5';       // 微信 H5
    case WechatPc        = 'wechat_pc';       // PC 网站应用
    case WechatApp       = 'wechat_app';      // 移动 App
    case WechatOpen      = 'wechat_open';     // 开放平台
    case WechatWork      = 'wechat_work';     // 企业微信
    case Qq              = 'qq';              // QQ

    // 阿里生态
    case AlipayMini      = 'alipay_mini';     // 支付宝小程序
    case AlipayMp        = 'alipay_mp';       // 支付宝生活号
    case AlipayApp       = 'alipay_app';      // 支付宝 App

    // 字节生态
    case DouyinMini      = 'douyin_mini';     // 抖音小程序
    case DouyinMp        = 'douyin_mp';       // 抖音头条号

    // 百度
    case BaiduMini       = 'baidu_mini';      // 百度智能小程序

    // 协同办公
    case Dingtalk        = 'dingtalk';        // 钉钉
    case Lark            = 'lark';            // 飞书
}
```

## 统一用户模型 (UnionUser)

```php
final readonly class UnionUser
{
    public string  $unionId;     // 统一 ID（跨平台识别）
    public string  $openId;      // 平台内 OpenID
    public Channel $channel;     // 来源渠道
    public ?string $nickname;    // 标准化昵称
    public ?string $avatar;      // 标准化头像
    public ?string $email;
    public ?string $phone;
    public ?string $gender;      // male / female / unknown
    public ?string $country;
    public ?string $province;
    public ?string $city;
    public array   $raw;         // 平台原始数据
    public array   $extra;       // 平台扩展信息

    public function toArray(): array;
    public function hasUnionId(): bool;
}
```

**关键设计**：
- `unionId` 是跨平台识别同一用户的"密钥"
- 平台字段（headimgurl / avatarUrl / figureurl 等）经 `UnionUser::fromRaw()` 归一化为标准字段
- `raw` 保留平台原始数据，便于业务侧处理平台特有逻辑
- `extra` 用于传递 access_token / refresh_token 等凭证

## 登录认证

```php
// 微信小程序
$user = $kernel->union()->authenticate(Channel::WechatMini, [
    'code' => 'JS_CODE',
]);

// 微信公众号
$user = $kernel->union()->authenticate(Channel::WechatMp, [
    'code' => 'OAUTH_CODE',
]);

// 微信 PC 扫码
$user = $kernel->union()->authenticate(Channel::WechatPc, [
    'code' => 'PC_SCAN_CODE',
]);

// 微信移动 App
$user = $kernel->union()->authenticate(Channel::WechatApp, [
    'code' => 'APP_AUTH_CODE',
]);

// 微信开放平台（代公众号 / 小程序）
$user = $kernel->union()->authenticate(Channel::WechatOpen, [
    'authorization_code'      => 'AUTH_CODE',
    'component_access_token'  => 'COMP_TOK',
]);

// 企业微信
$user = $kernel->union()->authenticate(Channel::WechatWork, [
    'code' => 'WORK_CODE',
]);

// QQ
$user = $kernel->union()->authenticate(Channel::Qq, [
    'code' => 'QQ_CODE',
]);

// 支付宝
$user = $kernel->union()->authenticate(Channel::AlipayMini, [
    'code' => 'ALIPAY_CODE',
]);

// 抖音
$user = $kernel->union()->authenticate(Channel::DouyinMini, [
    'code'           => 'DOUYIN_CODE',
    'anonymous_code' => 'ANON_CODE',  // 可选
]);

// 百度
$user = $kernel->union()->authenticate(Channel::BaiduMini, [
    'code' => 'BAIDU_CODE',
]);

// 钉钉
$user = $kernel->union()->authenticate(Channel::Dingtalk, [
    'code' => 'DING_CODE',
]);

// 飞书
$user = $kernel->union()->authenticate(Channel::Lark, [
    'code' => 'LARK_CODE',
]);
```

字符串形式：
```php
$user = $kernel->union()->login('wechat_mini', ['code' => 'JS_CODE']);
```

## 用户资料

```php
// 通过 openId 拉取用户资料
$user = $kernel->union()->profile(
    Channel::WechatMp,
    'OPEN_ID',
    [
        'access_token' => 'AUTH_ACCESS_TOKEN',
    ]
);
```

## 支付下单

```php
// 微信小程序支付
$result = $kernel->union()->pay(Channel::WechatMini)->unifiedOrder([
    'out_trade_no' => 'ORDER_001',
    'body'         => '商品',
    'total_fee'    => 100,  // 单位：分
    'openid'       => 'USER_OPENID',
    'notify_url'   => 'https://example.com/notify',
]);

// 微信 App 支付（开放平台移动应用）
$result = $kernel->union()->pay(Channel::WechatApp)->unifiedOrder([
    'out_trade_no'           => 'ORDER_002',
    'body'                   => '商品',
    'total_fee'              => 200,
    'authorizer_access_token' => 'AUTH_TOK',
    'authorizer_appid'        => 'wxapp0000000000',
    'notify_url'             => 'https://example.com/notify',
]);

// 支付宝
$result = $kernel->union()->pay(Channel::AlipayMini)->unifiedOrder([
    'out_trade_no' => 'ORDER_003',
    'subject'      => '商品',
    'total_amount' => '1.00',
    'notify_url'   => 'https://example.com/notify',
]);
```

## 回调通知

```php
// 微信支付回调
$decoded = $kernel->union()->notify(Channel::WechatMini)->decode(
    $request->all(),
    $request->headers->all()
);

// 支付宝回调
$decoded = $kernel->union()->notify(Channel::AlipayMini)->decode(
    $request->all(),
    $request->headers->all()
);
```

## 统一账号体系 (UnionID)

**关键概念**：
- 同一用户在不同平台登录，`openId` 不同但 `unionId` 相同（前提是平台支持 UnionID）
- 用 `unionId` 作为业务用户表的唯一键
- 微信生态（公众号/小程序/App/PC/QQ）共享 UnionID

**数据库设计建议**：

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    union_id VARCHAR(64) UNIQUE NOT NULL,
    nickname VARCHAR(128),
    avatar VARCHAR(255),
    created_at DATETIME,
    INDEX idx_union_id (union_id)
);

CREATE TABLE user_identities (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    channel VARCHAR(32) NOT NULL,     -- wechat_mini / alipay / douyin
    open_id VARCHAR(128) NOT NULL,
    union_id VARCHAR(64),
    extra JSON,
    UNIQUE KEY uk_channel_openid (channel, open_id),
    INDEX idx_user_id (user_id)
);
```

**登录流程**：
```php
$user = $kernel->union()->authenticate(Channel::WechatMini, ['code' => $code]);

// 1. 通过 unionId 找到主用户
$mainUser = User::firstOrCreate(
    ['union_id' => $user->unionId],
    ['nickname' => $user->nickname, 'avatar' => $user->avatar]
);

// 2. 记录平台身份
$mainUser->identities()->updateOrCreate(
    ['channel' => $user->channel->value, 'open_id' => $user->openId],
    ['union_id' => $user->unionId, 'extra' => $user->extra]
);
```

**跨端识别效果**：
- 用户先用微信小程序登录 → 业务表记录 `union_id = A`
- 用户再用 PC 扫码登录 → 拿到 `union_id = A` → 识别为同一用户
- 业务侧无需额外处理"账号合并"

## 自定义适配器

业务侧可注册自定义适配器扩展第三方平台：

```php
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

class WechatCorpLoginAdapter implements LoginAdapter
{
    public function channel(): Channel { return Channel::WechatWork; }

    public function authenticate(array $payload): UnionUser
    {
        // 自定义登录逻辑
        return new UnionUser(
            unionId: 'xxx',
            openId:  'xxx',
            channel: Channel::WechatWork,
        );
    }
}

$kernel->union()->registerLoginAdapter(new WechatCorpLoginAdapter());
```

类似的：
- `registerUserAdapter(UserAdapter)` - 用户资料
- `registerPayAdapter(PayAdapter)` - 支付
- `registerNotifyAdapter(NotifyAdapter)` - 回调

## 底层 vs 统一入口

| 场景 | 推荐方式 |
|---|---|
| 跨平台一键登录 | **统一入口** |
| 跨端账号合并 | **统一入口** |
| 单一平台业务 | 底层（更细粒度） |
| 自定义业务流 | 底层 |
| 复用一个 access_token 调多个接口 | 底层 |
| 复杂支付 / 退款流程 | 底层 |
| 平台特有接口（小程序订阅消息等） | 底层 |

**底层调用示例**：
```php
// 单一平台业务
$session = $kernel->wechat()->app()->auth()->session($code);
$user    = $kernel->wechat()->app()->subscribeMessage()->send([...]);

// 复杂业务流
$result = $kernel->wechatOpen()->app()->component()->queryAuth($token, $code);
```

**统一入口调用**：
```php
// 跨平台场景
$user = $kernel->union()->authenticate(Channel::WechatMini, ['code' => $code]);
```

**两者并存，按场景选用**。底层 Provider/App/Module 是统一入口的内部实现，统一入口是给业务侧的"快捷方式"。

## 完整示例：用户中心

```php
// 用户登录 - 不管从哪个端来
public function login(Request $request, Kernel $kernel): Response
{
    $channel = $request->input('channel');  // 来自前端
    $code    = $request->input('code');

    $user = $kernel->union()->login($channel, ['code' => $code]);

    // 业务逻辑：用 unionId 关联用户
    $localUser = $this->userService->findOrCreate([
        'union_id' => $user->unionId,
        'nickname' => $user->nickname,
        'avatar'   => $user->avatar,
    ]);

    // 记录身份
    $localUser->identities()->updateOrCreate([
        'channel' => $user->channel->value,
        'open_id' => $user->openId,
    ], [
        'union_id' => $user->unionId,
        'extra'    => $user->extra,
    ]);

    // 颁发业务 token
    return response()->json([
        'token'    => $this->tokenService->issue($localUser),
        'nickname' => $user->nickname,
        'avatar'   => $user->avatar,
    ]);
}
```

## 注意事项

1. **UnionID 跨平台前提**：
   - 同一开放平台账号下的应用
   - 支付宝、抖音、百度是独立账号体系（无 UnionID）
   - 钉钉、飞书是企业内部账号（userid 为准）

2. **敏感凭证**：
   - `session_key`（小程序）、`access_token`、`refresh_token` 放在 `extra` 中
   - 不应直接返回给前端
   - 后端应通过业务 token 颁发体系间接使用

3. **多端登录互踢**：
   - 业务侧应在数据库记录 `user_id + channel + device`
   - 同一 channel 重新登录可考虑"挤下线"

4. **回调幂等性**：
   - 支付回调必须做幂等（按 out_trade_no）
   - Union 入口的 `decode` 仅做数据归一化，不做幂等处理
