# QQ 使用文档

> 对应平台标识：`qq`
>
> 适用场景：QQ 小程序、QQ 网页应用

---

## 目录

1. [配置说明](#配置说明)
2. [登录认证](#登录认证)
3. [支付](#支付)

---

## 配置说明

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'qq' => [
        'app_id'  => 'your-app-id',   // QQ 应用 AppID
        'secret'  => 'your-secret',    // AppSecret
        'mch_id'  => 'your-mch-id',    // 支付商户号（可选）
        'api_key' => 'your-api-key',   // 支付 API 密钥（可选）
    ],
]);

$app = $kernel->qq()->app();
```

---

## 登录认证

### 获取用户信息

```php
// 用登录码换取用户信息
$user = $app->auth()->user($code);
// 返回：['openid' => 'xxx', 'session_key' => 'xxx', 'unionid' => 'xxx']

$openid = $user['openid'];
```

---

## 支付

> 2.0 起 QQ 支付完全由 `kode/pays` 承载（composer 硬依赖），付款人 openid 由本包登录后自动注入。

### 统一下单

```php
// 先登录拿到 UnionUser（openid 由桥接自动注入，无需手写）
$user  = $kernel->union()->qq()->mini($code);
$order = $kernel->union()->qq()->pay()->createOrder([
    'body'            => '商品描述',
    'out_trade_no'    => 'ORDER001',
    'total_fee'       => 100,  // 单位：分
    'spbill_create_ip'=> '127.0.0.1',
    'notify_url'      => 'https://example.com/notify',
    'trade_type'      => 'MINIAPP',
], $user);
```

### 查询订单

```php
$kernel->union()->qq()->pay()->queryOrder('ORDER001');
```

### 关闭订单

```php
$kernel->union()->qq()->pay()->closeOrder('ORDER001');
```

### 申请退款

```php
$kernel->union()->qq()->pay()->refund([
    'out_trade_no'  => 'ORDER001',
    'out_refund_no' => 'REFUND001',
    'total_fee'     => 100,
    'refund_fee'    => 50,
]);
```

---

## 更多参考

- [QQ 开放平台文档](https://q.qq.com/wiki/)
