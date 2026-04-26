# Kode MiniApp

多平台小程序、公众号、企业号统一接入 SDK。

[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)

## 特性

- **多平台统一接入**：一套代码对接微信、支付宝、抖音、百度、QQ、企业微信、钉钉、飞书
- **参考 EasyWeChat 设计**：借鉴其 Application/Server/Message 架构，但扩展至全平台
- **PHP 8.2+ 现代化**：使用 readonly、enum、match、构造函数属性提升等新特性
- **企业级能力**：除 C端小程序外，完整支持企业微信、钉钉、飞书的通讯录、审批、消息推送
- **服务端消息处理**：统一处理各平台的消息推送和事件回调
- **支付桥接**：内置基础支付能力，同时可桥接到 `kode/pays` 企业级聚合支付 SDK
- **工具桥接**：内置基础工具类，同时可桥接到 `kode/tools` 企业级工具包
- **异常桥接**：内置异常体系，同时可桥接到 `kode/exception` 统一异常处理组件
- **Kode 生态兼容**：与 kode/pays、kode/tools、kode/exception、kode/cache、kode/event 等包无缝协作

## Kode 生态关联

Kode MiniApp 是 Kode 生态的重要组成部分，与以下包可协同工作：

| 包名 | 类型 | 说明 |
|------|------|------|
| `kode/pays` | suggest | 企业级多平台聚合支付 SDK，安装后可通过 `payBridge()` 获取更强支付能力 |
| `kode/tools` | suggest | PHP 通用工具包（加解密、二维码、消息体等），安装后自动优先使用 |
| `kode/exception` | suggest | 统一异常处理组件，安装后扩展异常码体系 |
| `kode/cache` | suggest | 高性能缓存组件，支持 Redis/Memcached 等 |
| `kode/event` | suggest | 轻量级事件编排库，支持异步事件处理 |

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

| 平台 | 标识 | 类型 | 能力 |
|------|------|------|------|
| 微信 | `wechat` | C端 | 登录、JS-SDK、用户、素材、菜单、客服、消息、订阅消息、小程序码、数据分析、支付、服务端、回调通知 |
| 支付宝 | `alipay` | C端 | 登录、支付、转账、账单、回调通知 |
| 抖音 | `douyin` | C端 | 登录、支付 |
| 百度 | `baidu` | C端 | 登录、支付 |
| QQ | `qq` | C端 | 登录 |
| 微信企业号 | `wechat_work` | B端 | 认证、通讯录、部门管理、客户联系、外部联系人、标签、消息、审批、服务端、回调通知 |
| 钉钉 | `dingtalk` | B端 | 认证、通讯录、消息、审批、群机器人 |
| 飞书 | `lark` | B端 | 认证、通讯录、消息、审批、多维表格 |

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

// 支付宝下单（内置基础支付）
$order = $kernel->alipay()->app()->pay()->create([
    'out_trade_no' => 'ORDER_001',
    'total_amount' => '99.99',
    'subject'      => '测试商品',
]);

// 如安装了 kode/pays，可使用企业级支付能力
$pay = $kernel->wechat()->app()->payBridge();
if ($pay !== null) {
    // 使用 kode/pays 的企业级支付能力
    $pay->order([...]);
}
```

## 架构设计

```
Kernel（门面）
  └── Provider（平台入口）
        └── App（应用实例）
              ├── Auth（认证）
              ├── Pay（基础支付）
              ├── PayBridge（桥接 kode/pays 企业级支付）
              ├── Message（消息）
              ├── Contact（通讯录）
              ├── Approval（审批）
              ├── Jssdk（JS-SDK）
              ├── Server（服务端处理器）
              └── Notify（回调通知处理器）
```

### 核心组件

- **Kernel**：统一门面，通过 `$kernel->wechat()`、`$kernel->dingtalk()` 等快捷方法获取平台实例
- **Provider**：平台入口，管理配置和 HTTP 客户端，支持多应用实例
- **App**：应用实例，聚合该平台的所有能力模块
- **Server**：服务端消息处理器，统一处理消息推送和事件回调（参考 EasyWeChat）
- **Message**：消息构造器，构造被动回复消息
- **Notify**：支付回调通知处理器，自动验签并触发业务逻辑
- **PayBridge**：支付桥接器，自动检测并桥接到 `kode/pays` 企业级支付 SDK
- **ToolsBridge**：工具桥接器，自动检测并优先使用 `kode/tools` 工具类
- **ExceptionBridge**：异常桥接器，自动检测并扩展 `kode/exception` 异常码体系

## 各平台详细配置

### 微信

```php
'wechat' => [
    'app_id'     => 'wx1234567890',
    'secret'     => 'your-secret',
    'mch_id'     => '1234567890',
    'api_v3_key' => 'your-api-v3-key',
    'cert_path'  => '/path/to/apiclient_cert.pem',
    'key_path'   => '/path/to/apiclient_key.pem',
    'token'      => 'your-token',
    'aes_key'    => 'your-aes-key',
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

### 微信

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

// 发送模板消息
$app->message()->sendTemplate($openid, $templateId, ['thing1' => ['value' => '测试']]);

// 基础支付
$app->pay()->order([
    'description'  => '商品描述',
    'out_trade_no' => 'ORDER_001',
    'amount'       => ['total' => 100],
    'payer'        => ['openid' => $openid],
]);

// 企业级支付（需安装 kode/pays）
$pay = $app->payBridge();
if ($pay !== null) {
    $pay->order([...]);
}
```

### 微信服务端消息处理（参考 EasyWeChat）

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

// 客户联系
$customers = $app->customer()->list('zhangsan');
$detail = $app->customer()->detail($externalUserid);
$app->customer()->addContactWay([
    'type' => 1,
    'scene' => 1,
    'style' => 1,
    'remark' => '渠道客户',
]);

// 消息推送
$app->message()->text('Hello World', ['zhangsan']);
$app->message()->markdown('# 标题\n内容', ['zhangsan']);

// 审批
$app->approval()->template($templateId);
$app->approval()->apply($approvalData);
$app->approval()->detail($spNo);

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

// 审批
$app->approval()->instance($processInstanceId);
$app->approval()->create($data);
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

// 审批
$app->approval()->create($data);
$app->approval()->instance($instanceCode);
```

### 支付宝

```php
$app = $kernel->alipay()->app();

// 登录
$user = $app->auth()->token($code);
$userInfo = $app->auth()->user($accessToken);

// 基础支付
$app->pay()->create([
    'out_trade_no' => 'ORDER_001',
    'total_amount' => '99.99',
    'subject'      => '测试商品',
]);

// 企业级支付（需安装 kode/pays）
$pay = $app->payBridge();
if ($pay !== null) {
    $pay->create([...]);
}

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

// 回调通知
$result = $app->notify()
    ->onPaid(function ($payload) {
        $outTradeNo = $payload['out_trade_no'];
        // 更新订单...
    })
    ->handle();

echo 'success'; // 返回给支付宝
```

## Kode 生态桥接使用

### 支付桥接（kode/pays）

```php
use Kode\MiniApp\Bridge\PayBridge;

// 检查是否安装了 kode/pays
if (PayBridge::hasPayPackage()) {
    // 获取企业级支付实例
    $pay = $kernel->wechat()->app()->payBridge();
    $pay->order([...]);
    
    // 获取企业级通知处理器
    $notify = PayBridge::getNotify($kernel->wechat()->app());
}
```

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

AccessToken 自动缓存基于 Symfony Cache：

```php
use Kode\MiniApp\Core\Cache;
use Kode\MiniApp\Core\AccessToken;

// 使用内置缓存
$cache = Cache::getInstance('/custom/cache/path');
$tokenManager = new AccessToken($cache);

// 如安装了 kode/cache，可替换为高性能缓存
// composer require kode/cache
```

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
# 升级 patch 版本（0.5.0 -> 0.5.1）
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

1. `src/Contracts/Platform.php` 添加枚举值
2. `src/Providers/{Platform}/` 实现 Provider、App、Config、Modules
3. `src/Kernel.php` 注册 Provider
4. `tests/Providers/{Platform}/` 编写测试

## 与 EasyWeChat 的区别

| 特性 | EasyWeChat | Kode MiniApp |
|------|-----------|-------------|
| 平台支持 | 仅微信生态 | 微信、支付宝、抖音、百度、QQ、企业微信、钉钉、飞书 |
| 架构 | Application/Server/Message | 统一 Kernel + 多平台 Provider，同样支持 Server/Message/Notify |
| 定位 | 微信专用 SDK | 多平台统一接入 SDK |
| 企业能力 | 企业微信部分支持 | 企业微信、钉钉、飞书完整支持 |
| 支付回调 | 需自行处理 | 内置 Notify 处理器，自动验签 |
| 生态扩展 | 独立生态 | 与 kode/pays、kode/tools、kode/exception 等生态包无缝协作 |

## 许可证

Apache-2.0 License
