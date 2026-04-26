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

### 统一下单

```php
$app->pay()->unifiedOrder([
    'body'           => '商品描述',
    'out_trade_no'   => 'ORDER001',
    'total_fee'      => 100,  // 单位：分
    'spbill_create_ip'=> '127.0.0.1',
    'notify_url'     => 'https://example.com/notify',
    'trade_type'     => 'MINIAPP',
    'openid'         => $openid,
]);
```

### 查询订单

```php
$app->pay()->orderQuery('ORDER001');
```

### 关闭订单

```php
$app->pay()->closeOrder('ORDER001');
```

### 申请退款

```php
$app->pay()->refund([
    'out_trade_no'  => 'ORDER001',
    'out_refund_no' => 'REFUND001',
    'total_fee'     => 100,
    'refund_fee'    => 50,
]);
```

---

## 更多参考

- [QQ 开放平台文档](https://q.qq.com/wiki/)
