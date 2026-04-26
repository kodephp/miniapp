# 百度使用文档

> 对应平台标识：`baidu`
>
> 适用场景：百度智能小程序

---

## 目录

1. [配置说明](#配置说明)
2. [登录认证](#登录认证)
3. [支付](#支付)
4. [模板消息](#模板消息)

---

## 配置说明

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'baidu' => [
        'app_id'  => 'your-app-id',    // 百度应用 AppID
        'secret'  => 'your-secret',     // AppSecret
        'deal_id' => 'your-deal-id',    // 支付 DealID（可选）
        'pay_key' => 'your-pay-key',    // 支付 Key（可选）
    ],
]);

$app = $kernel->baidu()->app();
```

---

## 登录认证

### 获取 Session

```php
// 用登录码换取 Session
$session = $app->auth()->session($code);
// 返回：['openid' => 'xxx', 'session_key' => 'xxx']

$openid = $session['openid'];
```

### 获取用户信息

```php
// 用 AccessToken 获取用户信息
$userInfo = $app->auth()->userInfo($accessToken);
// 返回：['openid' => 'xxx', 'nickname' => '张三', 'headimgurl' => 'https://...']
```

---

## 支付

### 创建支付订单

```php
$order = $app->pay()->order([
    'dealId'      => 'DEAL001',
    'appKey'      => 'APP001',
    'totalAmount' => '100',
    'tpOrderId'   => 'ORDER_001',
    'dealTitle'   => '商品标题',
    'signFieldsValue' => '...',  // 签名值
]);
```

---

## 模板消息

### 发送模板消息

```php
$app->message()->send([
    'touser'      => $openId,
    'template_id' => $templateId,
    'page'        => 'pages/index/index',
    'data'        => [
        'keyword1' => ['value' => '值1'],
        'keyword2' => ['value' => '值2'],
        'keyword3' => ['value' => '值3'],
    ],
]);
```

### 模板管理

```php
// 获取模板列表
$app->message()->templateList();

// 获取模板详情
$app->message()->templateDetail($templateId);

// 删除模板
$app->message()->deleteTemplate($templateId);
```

---

## 更多参考

- [百度智能小程序文档](https://smartprogram.baidu.com/docs/)
