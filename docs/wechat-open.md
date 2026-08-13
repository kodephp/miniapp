# 微信开放平台 (Wechat Open Platform)

> Kode MiniApp SDK 内置对 **微信开放平台（第三方平台）** 的完整支持，可用于代公众号 / 小程序调用官方接口、统一管理多应用授权与 UnionID。

## 目录

- [快速开始](#快速开始)
- [配置说明](#配置说明)
- [核心能力模块](#核心能力模块)
  - [Component - 第三方平台自身能力](#component---第三方平台自身能力)
  - [Authorizer - 授权方管理](#authorizer---授权方管理)
  - [OpenApp - 移动应用 / 网站应用](#openapp---移动应用--网站应用)
  - [Crypto - 消息加解密](#crypto---消息加解密)
  - [UnionId - UnionID 机制工具](#unionid---unionid-机制工具)
- [微信生态互联](#微信生态互联)
- [完整授权流程示例](#完整授权流程示例)
- [API 验证对接](#api-验证对接)
- [常见问题](#常见问题)

## 快速开始

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'wechat_open' => [
        'component_appid'  => 'wxcomp1234567890',
        'component_secret' => 'your-component-appsecret',
        'token'            => 'your-verify-token',
        'encoding_aes_key' => '43-char-base64-encoding-aes-key', // 43位
    ],
    'wechat' => [
        'app_id'     => 'wxapp0000000000',
        'app_secret' => 'your-app-secret',
    ],
]);

$provider = $kernel->wechatOpen();
$app      = $provider->app();
```

## 配置说明

| 字段                  | 必填 | 说明                                              |
| --------------------- | ---- | ------------------------------------------------- |
| `component_appid`     | 是   | 微信开放平台第三方平台的 AppID                    |
| `component_secret`    | 是   | 第三方平台 AppSecret（可使用 `component_appsecret` 兼容字段） |
| `token`               | 是   | 消息校验 Token                                     |
| `encoding_aes_key`    | 是   | 消息加解密 EncodingAESKey（43 位 base64，可使用 `aes_key` 兼容字段） |
| `pre_auth_apps`       | 否   | 预授权的 appid 列表（数组）                         |

## 核心能力模块

### Component - 第三方平台自身能力

负责第三方平台自身的接口调用：`component_access_token`、`pre_auth_code`、授权页 URL、刷新授权方 token、查询授权信息等。

```php
/** @var \Kode\MiniApp\Providers\WechatOpen\WechatOpenApp $app */
$component = $app->component();

// 1. 获取 component_access_token（需用 component_verify_ticket）
$tokenResult = $component->accessToken($verifyTicket);
$componentAccessToken = $tokenResult['component_access_token'];

// 2. 获取预授权码
$preAuthResult = $component->preAuthCode($componentAccessToken);
$preAuthCode = $preAuthResult['pre_auth_code'];

// 3. 构造授权页 URL（业务方可重定向用户访问）
$loginUrl = $component->loginPage(
    preAuthCode:  $preAuthCode,
    redirectUri:  'https://example.com/callback',
    authType:     1, // 1=公众号/小程序，2=仅小程序，3=仅公众号
    bizAppId:     null,
    preAuthApps:  null,
);

// 4. 使用授权码换取 authorizer_access_token
$auth = $component->queryAuth($componentAccessToken, $authorizationCode);

// 5. 刷新 authorizer_access_token
$refresh = $component->refreshAuthorizerToken(
    $componentAccessToken,
    $auth['authorization_info']['authorizer_appid'],
    $auth['authorization_info']['authorizer_refresh_token'],
);

// 6. 获取授权方基本信息
$info = $component->authorizerInfo($componentAccessToken, $authorizerAppId);

// 7. 拉取所有已授权的公众号 / 小程序列表（自动翻页）
$authorizers = $component->allAuthorizers($componentAccessToken);
```

> 完整的微信开放平台 API 验证由 SDK 统一封装，开发者无需关心 token 拼接、签名验证、消息加解密等底层细节。

### Authorizer - 授权方管理

代公众号 / 小程序调用官方接口，统一管理授权方能力。

```php
$authorizer = $app->authorizer();
$authorizerAccessToken = 'AUTHORIZER_ACCESS_TOKEN';

// 代小程序登录（code2session）—— 必须传 component_access_token
$session = $authorizer->miniProgramSession(
    authorizerAppId: 'wxd1234567890',
    code: 'JS_CODE_FROM_CLIENT',
    componentAccessToken: $componentAccessToken,
);
// 返回含 session_key / openid / unionid（该小程序绑定开放平台时）

// 代公众号创建自定义菜单
$authorizer->createMenu(
    authorizerAccessToken: $authorizerAccessToken,
    buttons: [
        ['type' => 'click', 'name' => '今日歌曲', 'key' => 'V1001_TODAY_MUSIC'],
        ['type' => 'view', 'name' => '搜索', 'url' => 'http://www.soso.com/'],
    ],
);

// 代公众号发送客服消息
$authorizer->sendCustomerServiceMessage(
    authorizerAccessToken: $authorizerAccessToken,
    openId: 'OPENID_001',
    message: ['msgtype' => 'text', 'text' => ['content' => 'hello']],
);

// 代公众号发送模板消息
$authorizer->sendTemplateMessage(
    authorizerAccessToken: $authorizerAccessToken,
    toUser: 'OPENID_001',
    templateId: 'TEMPLATE_ID',
    data: ['first' => ['value' => '提醒'], 'remark' => ['value' => '点击查看']],
);

// 代小程序发送订阅消息
$authorizer->sendSubscribeMessage(
    authorizerAccessToken: $authorizerAccessToken,
    toUser: 'OPENID_001',
    templateId: 'SUBSCRIBE_TEMPLATE_ID',
    data: ['thing1' => ['value' => '订单提醒']],
);

// 代公众号获取用户信息
$userInfo = $authorizer->getUserInfo(
    authorizerAccessToken: $authorizerAccessToken,
    openId: 'OPENID_001',
);

// 通用透传调用（未封装接口）
$result = $authorizer->call(
    authorizerAccessToken: $authorizerAccessToken,
    path: '/custom/path',
    params: ['foo' => 'bar'],
    method: 'POST',
);
```

### OpenApp - 移动应用 / 网站应用

处理已绑定到开放平台的移动 App、网站应用扫码登录、access_token 换取等。

```php
$openApp = $app->openApp();

// 1. 网站应用扫码登录 URL
$loginUrl = $openApp->qrConnectUrl(
    appId: 'wx8888888888',
    redirectUri: 'https://example.com/callback',
    state: 'random_state',
);

// 2. 通过 code 换取网页 access_token
$token = $openApp->accessToken(
    appId: 'wx8888888888',
    secret: 'your-app-secret',
    code: 'CODE_FROM_CLIENT',
);

// 3. 拉取用户信息（snsapi_userinfo）
$userInfo = $openApp->userInfo(
    accessToken: $token['access_token'],
    openId: $token['openid'],
);

// 4. 刷新 access_token
$refreshed = $openApp->refreshToken(
    appId: 'wx8888888888',
    refreshToken: $token['refresh_token'],
);

// 5. 校验 access_token
$check = $openApp->authCheck(
    accessToken: $token['access_token'],
    openId: $token['openid'],
);
```

### Crypto - 消息加解密

实现微信开放平台官方推荐的 AES-256-CBC 加解密：处理 `component_verify_ticket` 推送、第三方平台授权事件、授权方消息回调等。

```php
$crypto = $app->crypto();

// 1. 验签并解密 component_verify_ticket 等推送
$plain = $crypto->decryptMessage(
    encrypted:    $_POST['Encrypt'],
    msgSignature: $_GET['msg_signature'],
    timestamp:    $_GET['timestamp'],
    nonce:        $_GET['nonce'],
);
// $plain 为 JSON 字符串，可按需解析

// 2. 加密被动回复消息
$payload = $crypto->encryptMessage(
    reply:     '<xml><Content>hello</Content></xml>',
    timestamp: (string) time(),
    nonce:     'random_nonce',
);
// $payload 为 JSON 字符串，包含 Encrypt / MsgSignature / TimeStamp / Nonce
```

### UnionId - UnionID 机制工具

辅助处理同一开放平台下多个应用的 UnionID 互通。

```php
$unionId = $app->unionId();

// 从授权方用户信息中提取 unionid
$uid = $unionId->fromPayload(['unionid' => 'UID_001']);

// 构造缓存键
$cacheKey = $unionId->cacheKey('UID_001', scope: 'app');
// => kode_wechat_unionid_app_UID_001

// 判断是否属于当前开放平台（提供已知授权方 appid 集合做真实归属判定）
$belongs = $unionId->belongsToCurrent(
    ['unionid' => 'UID_001', 'authorizer_appid' => 'wxd1234567890'],
    knownAuthorizerAppIds: ['wxd1234567890', 'wxo0987654321'],
);
```

## 微信生态互联

SDK 内置微信生态互联桥接：Wechat、WechatOpen、WechatWork、Qq 都属于微信生态。当 Kernel 同时配置多个平台时，可通过 `wechatOpen()`、`wechat()` 等方法互通。

```php
$kernel = new Kernel([
    'wechat'      => [...],
    'wechat_open' => [...],
    'wechat_work' => [...],
    'qq'          => [...],
]);

// 微信主 -> 开放平台
$wechatApp   = $kernel->wechat()->app();
$wechatOpen  = $wechatApp->wechatOpen();

// 开放平台 -> 微信主
$openApp     = $kernel->wechatOpen()->app();
$wechat      = $openApp->wechat();

// 企业微信 -> 微信 / 开放平台
$workApp     = $kernel->wechatWork()->app();
$wechat      = $workApp->wechat();
$wechatOpen  = $workApp->wechatOpen();

// QQ -> 微信（QQ 与微信账号体系互通）
$qqApp       = $kernel->qq()->app();
$wechat      = $qqApp->wechat();
```

> 平台枚举 `Platform::Wechat`、`Platform::WechatOpen`、`Platform::WechatWork`、`Platform::Qq` 共享微信生态（`Platform::isWechatEcosystem()` 返回 `true`）。

## 完整授权流程示例

```php
// 1. 用户在第三方平台授权页点击授权后，微信会回调 redirect_uri 并附上 authorization_code
// callback: https://example.com/wechat-open/callback?auth_code=xxx&expires_in=xxx

$kernel = new Kernel([...]);
$component = $kernel->wechatOpen()->app()->component();

// 2. 缓存 component_verify_ticket（微信每 10 分钟推送一次）
// 在你的推送处理脚本中：
$app = $kernel->wechatOpen()->app();
$crypto = $app->crypto();
$plain = $crypto->decryptMessage(
    encrypted:    $body['Encrypt'],
    msgSignature: $body['msg_signature'] ?? '',
    timestamp:    $body['CreateTime'] ?? (string) time(),
    nonce:        $body['Nonce'] ?? '',
);
$ticket = json_decode($plain, true)['ComponentVerifyTicket'];

// 3. 用 ticket 换 component_access_token 并缓存 2 小时
$tokenResult = $component->accessToken($ticket);
$componentAccessToken = $tokenResult['component_access_token'];

// 4. callback 中使用 authorization_code 换 authorizer_access_token
$authCode = $_GET['auth_code'];
$auth = $component->queryAuth($componentAccessToken, $authCode);
$authorizerAppId        = $auth['authorization_info']['authorizer_appid'];
$authorizerAccessToken  = $auth['authorization_info']['authorizer_access_token'];
$authorizerRefreshToken = $auth['authorization_info']['authorizer_refresh_token'];

// 5. 缓存 authorizer_access_token（2小时）和 authorizer_refresh_token（30天）

// 6. 代公众号 / 小程序调用接口
$authorizer = $kernel->wechatOpen()->app()->authorizer();
$userInfo   = $authorizer->getUserInfo(
    authorizerAccessToken: $authorizerAccessToken,
    openId: 'OPENID',
);
```

## API 验证对接

SDK 已对所有微信开放平台 API 做了完整验证封装，开发者无需关心：

- `component_access_token` 的获取、缓存、刷新
- 请求签名的生成与校验（`Component::verifySignature`、`Component::signForJsSdk`）
- 消息加解密的 PKCS#7 padding 处理（`Crypto::encryptMessage`、`Crypto::decryptMessage`）
- 授权流程的 URL 拼接（`Component::loginPage`）
- `authorizer_access_token` 的刷新机制（`Component::refreshAuthorizerToken`）

只需调用对应方法，传入必要参数即可。**所有 HTTP 请求、错误处理、签名验证均由 SDK 统一处理**。

## 常见问题

### Q1: `EncodingAESKey` 必须为 43 位吗？

是的，微信要求为 43 位 base64 字符串（不含末尾 `=`）。SDK 内部会校验 `strlen($binary) === 32`，不合法时抛出 `RuntimeException`。

### Q2: `component_verify_ticket` 如何接收？

微信每 10 分钟会主动 POST 推送到第三方平台填写的授权事件接收 URL。开发者需要：

1. 在控制器接收推送（XML 格式）
2. 用 `Crypto::decryptMessage` 解密
3. 解析 JSON 获取 `ComponentVerifyTicket`
4. 缓存到 Redis / 数据库，2 小时内使用

### Q3: 如何从 `appid` 找到对应的开放平台？

使用 `Platform::WechatOpen` 平台下的 `Component::authorizerInfo` 接口，传入 `authorizer_appid` 即可查询该 appid 所属的开放平台信息。

### Q4: 开放平台与小程序主体的区别？

- **开放平台（第三方平台）**：开发者作为服务商，代其他公众号 / 小程序实现业务
- **小程序主体**：开发者自己的小程序账号，直接调用微信接口

两者都通过 `Kernel` 统一管理，但 `Platform` 枚举不同：开放平台用 `WechatOpen`，普通小程序用 `Wechat`。
