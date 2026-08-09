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

        // 应用私钥（可选）：用于解密 code 换手机号等 RSA 密文数据，
        // 对应开放平台「开发配置-应用公钥」处录入的公钥。
        // 支持完整 PEM / 纯 Base64 / PKCS#1 / PKCS#8 四种写法。
        'app_private_key' => file_get_contents('/path/to/douyin_app_private_key.pem'),
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

### 获取手机号（code 换手机号）

自基础库 **3.51.0** 起，`<button open-type="getPhoneNumber">` 回调会返回动态令牌 `code`，服务端消费后即可拿到手机号，无需 `session_key`。

抖音返回的是**用应用公钥 RSA 加密的密文**，需先完成配置：在「抖音开放平台 - 控制台 - 开发 - 开发配置 - 应用公钥」录入公钥，并把对应私钥填入 `app_private_key`。

```php
// 前端 e.detail.code 回传服务端
$info = $app->phone()->byCode($code);

$info['phoneNumber'];      // +8613800138000（带区号）
$info['purePhoneNumber'];  // 13800138000（不带区号）
$info['countryCode'];      // 86
$info['watermark'];        // ['appid' => ..., 'timestamp' => ...]

// 便捷方法
$app->phone()->numberByCode($code);      // '+8613800138000'
$app->phone()->pureNumberByCode($code);  // '13800138000'
```

SDK 会自动获取并缓存开放平台 `client_token`（与小程序 `access_token` 是两套独立凭证），并在解密后校验 `watermark.appid`，防止跨应用重放。如需手动管理：

```php
$app->phone()->clientToken();        // 读取（命中缓存）
$app->phone()->refreshClientToken(); // 强制刷新
$app->phone()->forgetClientToken();  // 清除缓存
```

> - 每个 `code` 仅可消费一次，过期报 `28005187`；与 `tt.login` 的 code 作用不同、不可混用。
> - 平台更新应用公钥后会立即改用新公钥加密，须同步更新 `app_private_key`。
> - 应用私钥属最高级敏感凭证，严禁写入日志或下发客户端。
> - 旧版 `encryptedData` + `session_key` 解密（`$app->decrypt()->phone()`）仍可用，二者互为并行路径。

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
