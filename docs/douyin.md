# 抖音使用文档

> 对应平台标识：`douyin`
>
> 适用场景：抖音小程序、抖音网页应用

---

## 目录

1. [配置说明](#配置说明)
2. [登录认证](#登录认证)
3. [支付](#支付)
4. [视频管理](#视频管理)

---

## 配置说明

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'douyin' => [
        'app_id'    => 'ttxxxxxxxxxxxxxxxx',  // 抖音应用 AppID
        'secret'    => 'your-app-secret',      // AppSecret
        'salt'      => 'your-salt',            // 支付盐值（可选）
        'mch_id'    => 'your-mch-id',          // 支付商户号（可选）
        'pay_token' => 'your-pay-token',       // 支付 Token（可选）
    ],
]);

$app = $kernel->douyin()->app();
```

---

## 登录认证

### 获取用户信息

```php
// 用登录码换取用户信息
$user = $app->auth()->user($code);
// 返回：['open_id' => 'xxx', 'union_id' => 'xxx', 'session_key' => 'xxx', 'anonymous_open_id' => 'xxx']

$openId = $user['open_id'];
```

---

## 支付

### 创建支付订单

```php
$order = $app->pay()->create([
    'out_order_no' => 'ORDER_001',
    'total_amount' => 100,  // 单位：分
    'subject'      => '测试商品',
    'body'         => '商品描述',
    'valid_time'   => 3600,  // 订单有效期（秒）
]);

// 返回订单信息，前端调用 tt.pay({orderInfo: order})
```

### 查询订单

```php
$app->pay()->query('ORDER_001');
```

---

## 视频管理

### 上传视频

```php
// 上传视频文件
$app->video()->upload($accessToken, [
    'open_id' => $openId,
    'video'   => $videoData,  // 视频文件内容或路径
]);
```

### 创建视频

```php
// 发布视频
$app->video()->create($accessToken, [
    'open_id' => $openId,
    'item_id' => $itemId,       // 上传后返回的视频 ID
    'title'   => '视频标题',
    'cover'   => $coverUrl,     // 封面图地址
]);
```

### 查询视频列表

```php
// 获取用户发布的视频列表
$app->video()->list($accessToken, $openId, 0, 10);
// 参数：accessToken, openId, cursor, count
```

### 查询视频数据

```php
// 批量查询视频数据（播放量、点赞数等）
$app->video()->data($accessToken, $openId, [$itemId1, $itemId2]);
```

### 评论管理

```php
// 获取视频评论列表
$app->video()->commentList($accessToken, $openId, $itemId, 0, 10);

// 回复评论
$app->video()->commentReply($accessToken, [
    'open_id'    => $openId,
    'item_id'    => $itemId,
    'comment_id' => $commentId,
    'content'    => '感谢您的评论！',
]);
```

---

## 更多参考

- [抖音开放平台文档](https://developer.open-douyin.com/)
- [抖音小程序文档](https://developer.open-douyin.com/docs/resource/zh-CN/mini-app/introduction/usage-guide)
