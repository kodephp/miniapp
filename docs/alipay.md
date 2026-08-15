# 支付宝使用文档

> 对应平台标识：`alipay`
>
> 适用场景：支付宝小程序、支付宝网页支付、生活号

---

## 目录

1. [配置说明](#配置说明)
2. [登录认证](#登录认证)
3. [支付（kode/pays）](#支付kodepays)
4. [转账](#转账)
5. [账单](#账单)
6. [营销](#营销)
7. [会员](#会员)
8. [支付回调通知](#支付回调通知)

---

## 配置说明

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'alipay' => [
        'app_id'      => '2024xxxxxxxxxxxx',   // 支付宝应用 AppID
        'private_key' => 'your-private-key',    // 应用私钥（RSA2）
        'public_key'  => 'alipay-public-key',   // 支付宝公钥
        'sandbox'     => false,                 // 是否沙箱环境
    ],
]);

$app = $kernel->alipay()->app();
```

---

## 登录认证

### 获取 AccessToken

```php
// 用授权码换取 AccessToken
$token = $app->auth()->token($code);
// 返回：['access_token' => 'xxx', 'expires_in' => 3600, 'refresh_token' => 'xxx']

$accessToken = $token['access_token'];
```

### 获取用户信息

```php
// 用 AccessToken 获取用户信息
$userInfo = $app->auth()->user($accessToken);
// 返回：['user_id' => '2088xxx', 'nick_name' => '张三', 'avatar' => 'https://...']
```

---

## 支付（kode/pays）

> 2.0 起支付宝支付完全由 `kode/pays` 承载（composer 硬依赖），下单 / 验签 / 退款 / 分账 / 转账 / 对账统一经 kode/pays。付款人身份（buyer_id）由本包登录后自动注入。

### 创建支付订单

```php
// 先登录拿到 UnionUser（buyer_id 由桥接自动注入）
$user  = $kernel->union()->alipay()->appInstance()->auth()->token($code);
$order = $kernel->union()->alipay()->pay()->createOrder([
    'out_trade_no' => 'ORDER_001',
    'total_amount' => '99.99',
    'subject'      => '测试商品',
    'body'         => '商品详细描述',
    'product_code' => 'QUICK_MSECURITY_PAY',  // 小程序支付
], $user);

// 返回订单字符串，前端调用 my.tradePay({orderStr: orderString})
```

### 查询订单

```php
$result = $kernel->union()->alipay()->pay()->queryOrder('ORDER_001');
// 返回：['trade_no' => '2024xxx', 'out_trade_no' => 'ORDER_001', 'total_amount' => '99.99', 'trade_status' => 'TRADE_SUCCESS']
```

### 关闭订单

```php
$kernel->union()->alipay()->pay()->closeOrder('ORDER_001');
```

### 退款 / 分账 / 转账

```php
$pay = $kernel->union()->alipay()->pay();
$pay->refund([
    'out_trade_no'  => 'ORDER_001',
    'refund_amount' => '99.99',
    'refund_reason' => '用户申请退款',
]);
// 分账 / 转账等能力由 kode/pays 网关直接提供
```

---

## 转账

### 单笔转账

```php
$app->transfer()->create([
    'out_biz_no'    => 'TRANSFER_001',
    'trans_amount'  => '100.00',
    'order_title'   => '用户提现',
    'payee_account' => 'user@example.com',  // 支付宝账号
    'payee_name'    => '张三',
    'biz_scene'     => 'DIRECT_TRANSFER',
    'product_code'  => 'TRANS_ACCOUNT_NO_PWD',
]);
```

### 查询转账

```php
$app->transfer()->query('TRANSFER_001');
```

---

## 账单

### 下载账单

```php
// 下载交易账单
$app->bill()->download('trade', '2024-01-01');

// 下载资金账单
$app->bill()->download('fund', '2024-01-01');
```

---

## 营销

### 现金红包活动

```php
// 创建现金活动
$app->marketing()->createCashActivity([
    'coupon_name' => '现金红包活动',
    'prize_type'  => 'FIX',       // FIX=固定金额，RANDOM=随机金额
    'total_money' => 10000,       // 单位：分
    'total_num'   => 100,
]);
```

### 触发红包

```php
$app->marketing()->triggerCash([
    'user_id'    => $userId,
    'out_biz_no' => 'BIZ001',
    'amount'     => 100,  // 单位：分
]);
```

### 优惠券

```php
// 创建优惠券模板
$app->marketing()->createVoucherTemplate([
    'voucher_name' => '优惠券模板',
    'brand_name'   => '商家名称',
]);

// 发送优惠券
$app->marketing()->sendVoucher([
    'voucher_template_id' => $templateId,
    'user_id'             => $userId,
]);
```

### 扫码支付

```php
// 线下扫码支付预创建
$app->marketing()->precreate([
    'out_trade_no' => 'ORDER001',
    'total_amount' => '100.00',
    'subject'      => '商品标题',
]);
```

### 退款

```php
$app->marketing()->refund([
    'out_trade_no' => 'ORDER001',
    'refund_amount'=> '50.00',
]);
```

---

## 会员

### 获取会员信息

```php
// 用 AccessToken 获取会员信息
$app->member()->info($accessToken);
// 返回：['user_id' => '2088xxx', 'nick_name' => '张三', ...]
```

### 查询授权信息

```php
$app->member()->authInfo($authToken);
```

### 查询积分余额

```php
$app->member()->pointBalance($userId);
// 返回：['balance' => 1000, 'pending_balance' => 100]
```

---

## 支付回调通知

```php
$notify = $app->notify();

$result = $notify
    ->onPaid(function ($payload) {
        $outTradeNo = $payload['out_trade_no'];
        $tradeStatus = $payload['trade_status'];

        if ($tradeStatus === 'TRADE_SUCCESS') {
            // TODO: 更新订单状态
        }

        return true;
    })
    ->handle();

// 返回给支付宝
echo 'success';
```

---

## 更多参考

- [支付宝开放平台文档](https://opendocs.alipay.com/)
- [支付宝小程序文档](https://opendocs.alipay.com/mini/)
