# Union 统一调用入口

> **设计目标**：业务侧只需要 `use Kode\MiniApp\Union\Union;` 一行代码，通过静态方法即可访问所有平台，
> 一行代码完成跨平台登录 / 支付 / 回调，跨端账号自动合并（UnionID）。

## 为什么需要 Union 统一入口

在传统 SDK 中，业务侧需要：

```php
// 原来需要 use 很多模块
use Kode\MiniApp\Providers\Wechat\Modules\Auth;
use Kode\MiniApp\Providers\Wechat\Modules\Pay;
use Kode\MiniApp\Providers\Wechat\WechatProvider;
use Kode\MiniApp\Providers\Alipay\AlipayProvider;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenProvider;

// 微信小程序登录
$wechat = $kernel->wechat();
$app = $wechat->app();
$session = $app->auth()->session($code);
$openId = $session['openid'];
$unionId = $session['unionid'] ?? '';

// 微信公众号 OAuth 登录 - 又是一套不同接口
$accessToken = $app->auth()->token($code);
$userInfo = $app->user()->info($accessToken, $openId);

// 跨端账号合并 - 手动处理
// 用户先用小程序登录，又用 PC 扫码 - 需要手动查数据库合并
```

**使用 Union 后**：

```php
use Kode\MiniApp\Union\Union;

// 微信小程序登录 - 一行搞定
$user = Union::wechat()->mini($code);

// 微信公众号 - 同样一行
$user = Union::wechat()->mp($code);

// PC 扫码 - 同样一行
$user = Union::wechat()->pc($code);

// 跨端账号自动合并（同一 unionId）
$user1 = Union::wechat()->mini($code);  // unionId: u001
$user2 = Union::wechat()->pc($code);    // unionId: u001 (相同)
```

## 快速开始

```php
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Union\Union;

// 1. 初始化
$kernel = new Kernel([
    'wechat'       => [...],
    'wechat_open'  => [...],
    'alipay'       => [...],
    // ...其他平台配置
]);
$kernel->union();  // 触发 Union 初始化（之后即可用静态方法）

// 2. 业务侧只需要 use Union 类
$user = Union::wechat()->mini('JS_CODE');      // 微信小程序
$user = Union::alipay()->mini('AUTH_CODE');    // 支付宝
$order = Union::wechat()->pay()->unifiedOrder([...]);
```

## Union 静态门面

通过 `__callStatic` 魔术方法，Union 类把平台方法统一为：

| 静态方法 | 返回 | 说明 |
|----------|------|------|
| `Union::wechat()` | `WechatUnion` | 微信生态聚合 |
| `Union::wechatOpen()` / `Union::openPlatform()` | `WechatOpenPlatformUnion` | 微信开放平台 |
| `Union::alipay()` | `AlipayUnion` | 支付宝聚合 |
| `Union::douyin()` | `DouyinUnion` | 抖音聚合 |
| `Union::baidu()` | `BaiduUnion` | 百度智能小程序 |
| `Union::qq()` | `QqUnion` | QQ 聚合 |
| `Union::wechatWork()` / `Union::work()` | `WechatWorkUnion` | 企业微信聚合 |
| `Union::dingtalk()` | `DingtalkUnion` | 钉钉聚合 |
| `Union::lark()` | `LarkUnion` | 飞书聚合 |

## 平台聚合类（Platform Union）

每个平台聚合类（`WechatUnion`、`AlipayUnion` 等）提供以下统一方法：

### 场景登录

| 方法 | 适用场景 |
|------|----------|
| `->mini($code)` | 小程序 / 默认登录 |
| `->mp($code)` | 公众号 |
| `->h5($code)` | H5 |
| `->pc($code)` | PC 网站应用 |
| `->app($code)` | 移动 App |
| `->open($payload)` | 开放平台 |
| `->suite($code)` | 企业微信套件 |
| `->login($payload, $scene)` | 通用登录（自定义 payload + 场景） |

### 四大能力

```php
// 1. 登录 - 统一返回 UnionUser
$user = Union::wechat()->mini($code);

// 2. 用户资料 - 通过 openId 拉取
//    公众号 / H5：自动解析 mp access_token，无需手动传入
$user = Union::wechat()->user($openId, [], 'mp');
//    小程序：服务端无法拉取，使用客户端上报（已解密）的资料
$user = Union::wechat()->user($openId, ['raw' => $clientUserInfo], 'mini');
//    开放平台移动 / 网站应用：传入登录时获取的 OAuth access_token
$user = Union::wechat()->user($openId, ['access_token' => $token], 'app');

// 3. 支付 - 统一下单
$order = Union::wechat()->pay()->unifiedOrder([
    'out_trade_no' => 'O001',
    'body'         => '商品',
    'total_fee'    => 100,
    'openid'       => $openId,
]);
// 简写
$order = Union::wechat()->unifiedOrder([...]);

// 4. 回调 - 验证 + 解析
$data = Union::wechat()->notify()->decode($payload, $headers);
```

### 透传底层 Provider / App

如需细粒度控制（如素材管理、菜单管理、JS-SDK 等）：

```php
$wechat = Union::wechat();

// 获取平台 App 实例（含 30+ 能力模块）
$app = $wechat->appInstance();
$app->message()->send($openId, 'text', 'Hello');
$app->media()->upload('image', '/path/to/image.jpg');
$app->menu()->create([...]);
$app->jssdk()->config($url, ['chooseImage']);
$app->subscribeMessage()->send($openId, $templateId, $data);

// 获取 Provider
$provider = $wechat->provider();
```

## 跨端账号合并（UnionID）

配置好微信开放平台（同一开放平台下绑定所有应用）后，跨端账号自动合并：

```php
// 用户先用小程序登录
$user1 = Union::wechat()->mini('JS_CODE');        // unionId: u001

// 再用 PC 扫码
$user2 = Union::wechat()->pc('PC_CODE');          // unionId: u001 (相同)
$user3 = Union::wechat()->app('APP_CODE');        // unionId: u001 (相同)
$user4 = Union::wechat()->h5('H5_CODE');          // unionId: u001 (相同)
$user5 = Union::wechat()->mp('OAUTH_CODE');       // unionId: u001 (相同)

// 业务侧只需用 unionId 关联业务账号
$businessUser = User::where('union_id', $user1->unionId)->first();
```

## 微信开放平台绑定：公众号 / 小程序一键登录 + 用户信息

将公众号、小程序、移动 App、网站应用绑定到**同一个微信开放平台**后，同一用户在各端登录都会得到相同的 `unionId`，业务侧据此关联同一用户（即"一键登录、多端通用"）。

```php
use Kode\MiniApp\Union\Union;

// 1) 一键登录：各端一行代码，自动返回相同的 unionId
$mini = Union::wechat()->mini('JS_CODE');   // 小程序
$mp   = Union::wechat()->mp('OAUTH_CODE');  // 公众号 OAuth
$pc   = Union::wechat()->pc('PC_CODE');     // 网站应用扫码
$app  = Union::wechat()->app('APP_CODE');   // 移动 App

// 2) 获取用户信息：登录后通过 openId 拉取
//    公众号 / H5：无需手动传 token，适配器自动解析 mp access_token
$profile = Union::wechat()->user($mp->openId, [], 'mp');
//    小程序：服务端无法拉取，使用客户端上报（已解密）的资料
$profile = Union::wechat()->user($mini->openId, ['raw' => $clientUserInfo], 'mini');
//    开放平台 App / PC：传入登录时获取的 OAuth access_token
$profile = Union::wechat()->user($app->openId, ['access_token' => $oauthToken], 'app');

// 3) 关联业务账号：unionId 在所有绑定应用中一致
$bizUser = User::where('union_id', $mini->unionId)->first();
```

> 注意：小程序没有服务端用户资料接口，`nickname` / `avatar` 需由客户端通过 `wx.getUserProfile` 取得后随登录一并上报（经 `raw` 传入）。

### 错误处理（真实对接）

所有平台登录均按各开放平台真实接口契约校验错误，避免「无效 code / 过期 token」被静默当成成功：

- **微信系**：小程序 `jscode2session`、公众号 `sns/oauth2/access_token`、开放平台 App/PC `sns/oauth2/access_token` 在微信返回 `errcode`（如 `40029 invalid code`、`40013 invalid appid`）时抛出 `Kode\MiniApp\Exceptions\ApiException`。
- **支付宝**：网关错误响应挂在独立的 `error_response` 节点（不以 `alipay_` 开头），SDK 已专门识别并在 `code != 10000` 时抛出 `ApiException`（此前该节点未被识别，错误会被静默吞掉）。支付宝无 unionid 概念，`unionId` 恒为空。
- **抖音 / QQ / 百度 / 企业微信 / 钉钉 / 飞书**：分别按 `err_no` / `errcode` / `error`(OAuth) / `errcode` / `errcode` / `code` 字段校验，失败时同样抛出 `ApiException`。
- **用户资料拉取（profile）同样校验错误**：抖音 `apps/v2/user/get_profile`（`err_no`）、QQ `graph.qq.com/user/get_user_info`（错误字段为 `ret`，与登录接口的 `errcode` 不同）、百度 `smartapp/getuserinfo`（错误字段为 `errno`，与授权接口的 `error` 不同）、企业微信 `cgi-bin/user/get`（`errcode`）、**微信 `cgi-bin/user/info` 与开放平台 `sns/userinfo`（`errcode`，`48001` 为预期内空资料不抛错，其余如 `40001` 令牌失效统一抛 `ApiException`）**、支付宝 `alipay.user.info.share`（错误节点 `error_response`）、钉钉 `topapi/v2/user/get`（`errcode`）、飞书 `contact/v3/users`（`code`），均在校验失败后抛出 `ApiException`，彻底杜绝资料接口的静默失败。QQ / 百度需调用方传入用户 `access_token`（payload `access_token`）；抖音、微信、企业微信、支付宝、钉钉、飞书未传时自动回退到各自服务端 token。
- **飞书嵌套字段归一化**：飞书 `contact/v3/users` 返回的 `name`（对象：`zh_cn`/`en_us`）与 `avatar`（对象：`avatar_origin`/`avatar_240`/`avatar_72`）为嵌套结构，适配器已将其归一化为 `nick_name` / `avatar_url`，避免昵称与头像被静默丢失（该 bug 在 v1.20.0 修复）。
- **企业微信资料修复**：此前 `Channel::WechatWork` 的 profile 被错误路由到微信（Wechat）适配器，根本取不到企业微信成员资料（要么调用错误 Provider、要么静默返回空）。现已新增独立的 `WeWorkUserAdapter`，以 userid 为键调 `/user/get` 拉取姓名 / 头像 / 部门 / 职位等真实资料。
- 开放平台「第三方平台代公众号/小程序」授权（`authorization_code` 换 `authorizer_access_token`）在微信返回 `errcode` 时抛出 `RuntimeException`，错误信息含 `errmsg`。
- 拉取用户资料（`sns/userinfo` / `cgi-bin/user/info`）若授权范围不足（如 `48001` 用户未关注 / 未授权 userinfo）不会抛错，仅返回空资料，由业务侧决定是否补充授权；但 `40001`（令牌失效）、`40003`（openid 非法）、`50001`（未授权）等真实错误现已统一抛出 `ApiException`（此前被静默吞进占位 `UnionUser`，业务侧无法感知）。该「预期内错误 vs 真实错误」的判定收敛在 `WechatProfileError` 一处，微信系所有资料拉取路径复用。

> 端到端测试覆盖：`tests/Union/WechatLoginTest.php`、`tests/Union/OpenPlatformLoginTest.php`（微信系）、`tests/Union/PlatformLoginTest.php`（支付宝 / 抖音 / QQ / 百度 / 企业微信 / 钉钉 / 飞书 登录）、`tests/Union/UserProfileTest.php`（微信 mp / 微信开放平台 App + 抖音 / QQ / 百度 / 企业微信 / 支付宝 / 钉钉 / 飞书 用户资料拉取），每个渠道均验证「成功提取 openid/unionid/昵称/头像」与「无效 code / token / userid / openid 真实抛错」。

```php
use Kode\MiniApp\Exceptions\ApiException;

try {
    $user = Union::wechat()->app('APP_CODE');
} catch (ApiException $e) {
    // $e->errorCode() 为微信 errcode，$e->message() 为 errmsg
    echo $e->errorCode() . ': ' . $e->message();
}
```

## 客户端敏感数据解密（encryptedData）

小程序端 `getUserProfile` / `getPhoneNumber` 等接口回传的 `encryptedData` 是**AES 加密的 JSON**，必须由服务端用登录阶段 `jscode2session` 拿到的 `session_key` 解密。微信、抖音、QQ 三端采用**同一套对称算法**（`AES-128-CBC` + `PKCS#7` + `watermark`），SDK 统一封装为共享工具 `Core\Crypto\Aes128CbcPkcs7`，三端 `Decrypt` 模块复用，杜绝重复实现。

- **算法**：`AES-128-CBC`，`key = base64_decode(session_key)`、`iv = base64_decode(iv)`，密文 `base64_decode(encryptedData)`，PKCS#7 由 openssl 自动去填充。
- **安全约束**：解密结果含 `watermark.appid`，必须与当前小程序 `appId` 一致，否则视为伪造数据并抛 `ApiException`。生产环境务必保持默认开启（`verifyAppId = true`）。
- **敏感凭证**：`session_key` 属密钥级敏感信息，**切勿下发到前端、切勿写入日志**（`LogSanitizer` 已对 `session_key` 脱敏）。

### 支持的渠道

| 渠道 | Union 入口 | 直接模块 |
| --- | --- | --- |
| 微信小程序 / 公众号 / 开放平台 | `Channel::WechatMini` / `WechatMp` / `WechatApp` | `WechatApp::decrypt()` |
| 抖音小程序 / 抖音 PC | `Channel::DouyinMini` / `DouyinMp` | `DouyinApp::decrypt()` |
| QQ 小程序 | `Channel::Qq` | `QqApp::decrypt()` |

其余渠道（支付宝 / 百度 / 钉钉 / 飞书等）调用 `decrypt()` 抛 `InvalidArgumentException`。

### 用法一：通过 Union 统一入口

```php
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;

// 前端回传：encryptedData、iv、登录阶段缓存的 session_key
$data = Union::wechat()->decrypt(Channel::WechatMini, $encryptedData, $sessionKey, $iv);
// 抖音
$data = Union::douyin()->decrypt(Channel::DouyinMini, $encryptedData, $sessionKey, $iv);
// QQ
$data = Union::qq()->decrypt(Channel::Qq, $encryptedData, $sessionKey, $iv);
// $data['nickName'] / $data['avatarUrl'] / $data['watermark'] ...

// 手机号（getPhoneNumber）更严格的字段校验
$phone = Union::wechat()->decrypt(Channel::WechatMini, $encryptedData, $sessionKey, $iv)
    ->phone(...); // 也可用 $app->decrypt()->phone()
// $phone['phoneNumber'] / $phone['countryCode'] ...
```

### 用法二：直接走 App 模块

```php
use Kode\MiniApp\Kernel;

$app = (new Kernel(['wechat' => ['app_id' => 'wx...', 'app_secret' => '...']]))->wechat()->app();

$raw  = $app->decrypt()->data($encryptedData, $sessionKey, $iv);   // 原始数组（含 watermark 校验）
$user = $app->decrypt()->userInfo($encryptedData, $sessionKey, $iv); // 同 data()，语义化别名
$phone = $app->decrypt()->phone($encryptedData, $sessionKey, $iv);  // 手机号，缺字段抛 ApiException
```

抖音 / QQ 的 `Decrypt` 模块提供完全一致的 `data()` / `userInfo()` / `phone()` 三方法。

### 失败语义（统一抛 ApiException）

| 场景 | 行为 |
| --- | --- |
| `watermark.appid` 与当前 `appId` 不一致 | 抛 `ApiException`（伪造数据） |
| `session_key` / `iv` / `encryptedData` base64 非法 | 抛 `ApiException` |
| 密钥或向量长度非 16 字节 | 抛 `ApiException` |
| `session_key` 错误导致解密出乱码（非 JSON） | 抛 `ApiException` |
| 手机号密文缺少 `phoneNumber` 等字段 | 抛 `ApiException` |
| 需临时关闭 appId 校验（verifyAppId=false） | 返回原始数组，不校验（仅特殊场景） |

> 端到端测试：`tests/Providers/{Wechat,Douyin,Qq}/DecryptTest.php`（各端真实 AES round-trip、手机号、watermark 校验失败、错误密钥、非法 base64 / 长度）、`tests/Union/DecryptTest.php`（微信 / 抖音 / QQ 分派成功 + 不支持渠道抛错）。加密向量采用与三端官方完全一致的算法生成，即是对「真实对接」的端到端验证。

## 自定义适配器（业务扩展）

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

## 底层 vs 统一入口

| 维度 | 底层 Provider / App | Union 统一入口 |
|------|--------------------|-----------------|
| 适用对象 | 框架开发者、扩展定制 | 业务开发者（**99% 场景**） |
| 能力粒度 | 30+ 模块细粒度 | 场景登录 + 4 大能力 + 透传 Provider |
| 学习成本 | 高（需了解每个模块） | 极低（几行代码搞定） |
| 跨平台切换 | 需重写业务 | 无缝切换 |
| UnionID 处理 | 手动 | 自动 |
| 静态调用 | 不支持 | 支持 `Union::wechat()` |

> **设计哲学**：底层 Provider/App 是 SDK 的"零件库"，Union 是面向业务场景的"成品工具"。
> 业务侧 99% 的需求都可以用 Union 解决，仅在需要极细粒度控制时才用底层 Provider。

## 完整调用示例

```php
use Kode\MiniApp\Union\Union;

// ===== 1. 跨平台登录 =====
$user1 = Union::wechat()->mini('JS_CODE');           // 微信小程序
$user2 = Union::wechat()->mp('CODE');                // 公众号
$user3 = Union::wechat()->pc('PC_CODE');             // PC 扫码
$user4 = Union::wechat()->h5('H5_CODE');             // H5
$user5 = Union::wechat()->app('APP_CODE');           // 移动 App

$user6 = Union::alipay()->mini('AUTH_CODE');         // 支付宝小程序
$user7 = Union::alipay()->mp('CODE');                // 支付宝生活号
$user8 = Union::alipay()->login(['code' => $code, 'channel' => 'alipay_app']);  // 支付宝 App

$user9  = Union::douyin()->mini('CODE');             // 抖音小程序
$user10 = Union::baidu()->mini('CODE');              // 百度小程序
$user11 = Union::qq()->mini('CODE');                 // QQ 小程序
$user12 = Union::work()->login('CODE');              // 企业微信
$user13 = Union::dingtalk()->mini('CODE');           // 钉钉
$user14 = Union::lark()->mini('CODE');               // 飞书

// ===== 2. 跨平台支付 =====
$order1 = Union::wechat()->pay()->unifiedOrder([
    'out_trade_no' => 'O001',
    'body'         => '商品',
    'total_fee'    => 100,
    'openid'       => $user1->openId,
]);
$order2 = Union::alipay()->pay()->unifiedOrder([...]);
$order3 = Union::work()->pay()->unifiedOrder([...]);

// ===== 3. 跨平台回调 =====
$data1 = Union::wechat()->notify()->decode($payload, $headers);
$data2 = Union::alipay()->notify()->decode($payload, $headers);

// ===== 4. 跨平台用户资料 =====
// 公众号 / H5 自动解析 mp access_token；小程序传客户端上报数据；App / PC 传登录 token
$user1 = Union::wechat()->user($openId, [], 'mp');
$user2 = Union::alipay()->user($openId, ['access_token' => $token]);

// ===== 5. 细粒度访问（30+ 模块） =====
$app = Union::wechat()->appInstance();
$app->message()->sendSubscribe($openId, $templateId, [...]);
$app->media()->upload('image', '/path/to/image.jpg');
$app->menu()->create([...]);
$app->jssdk()->config($url, ['chooseImage']);
```

## 架构设计

```
Union 静态门面（__callStatic 调度）
  ├── wechat()       → WechatUnion       → WechatProvider      → WechatApp (30+ 模块)
  ├── alipay()       → AlipayUnion       → AlipayProvider      → AlipayApp
  ├── douyin()       → DouyinUnion       → DouyinProvider      → DouyinApp
  ├── baidu()        → BaiduUnion        → BaiduProvider       → BaiduApp
  ├── qq()           → QqUnion           → QqProvider          → QqApp
  ├── wechatWork()   → WechatWorkUnion   → WechatWorkProvider  → WechatWorkApp
  ├── dingtalk()     → DingtalkUnion     → DingtalkProvider    → DingtalkApp
  ├── lark()         → LarkUnion         → LarkProvider        → LarkApp
  └── wechatOpen()   → WechatOpenPlatformUnion → WechatOpenProvider → WechatOpenApp
```

**统一账号体系**：

```
用户在小程序登录       用户在公众号登录       用户在 PC 扫码       用户在 App 登录
       ↓                     ↓                    ↓                    ↓
   jscode2session        OAuth 网页授权        扫码回调            App 授权回调
       ↓                     ↓                    ↓                    ↓
   Union::wechat()->mini   Union::wechat()->mp   Union::wechat()->pc  Union::wechat()->app
       ↓                     ↓                    ↓                    ↓
       └─────────────────────┴────────────────────┴────────────────────┘
                                       ↓
                                 统一 UnionUser
                                  ├─ unionId: u001 (跨端相同)
                                  ├─ openId:  wx_xxx (平台内唯一)
                                  ├─ channel: wechat_mini / wechat_mp / wechat_pc / wechat_app
                                  ├─ nickname, avatar, ...
                                  └─ raw / extra (平台原始数据)
```
