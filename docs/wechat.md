# 微信（公众号 / 小程序）使用文档

> 对应平台标识：`wechat`
>
> 适用场景：微信小程序、微信公众号、微信网页开发

---

## 目录

1. [配置说明](#配置说明)
2. [登录认证](#登录认证)
3. [JS-SDK](#js-sdk)
4. [用户管理](#用户管理)
5. [素材管理](#素材管理)
6. [菜单管理](#菜单管理)
7. [客服消息](#客服消息)
8. [消息推送](#消息推送)
9. [订阅消息](#订阅消息)
10. [小程序码](#小程序码)
11. [数据分析](#数据分析)
12. [支付](#支付)
13. [订单物流同步](#订单物流同步)
14. [内容安全](#内容安全)
15. [URL Scheme / Link](#url-scheme--link)
16. [插件管理](#插件管理)
17. [直播](#直播)
18. [附近小程序](#附近小程序)
19. [门店小程序](#门店小程序)
20. [卡券](#卡券)
21. [摇一摇](#摇一摇)
22. [发票](#发票)
23. [连 Wi-Fi](#连-wi-fi)
24. [微信小店](#微信小店)
25. [红包](#红包)
26. [广告](#广告)
27. [即时配送](#即时配送)
28. [搜一搜](#搜一搜)
29. [动态消息](#动态消息)
30. [设备功能](#设备功能)
31. [云开发](#云开发)
32. [服务端消息处理](#服务端消息处理)
33. [支付回调通知](#支付回调通知)

---

## 配置说明

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'wechat' => [
        'app_id'     => 'wx1234567890abcdef',  // 小程序/公众号 AppID
        'secret'     => 'your-app-secret',       // AppSecret
        'mch_id'     => '1234567890',            // 微信支付商户号（可选）
        'api_v3_key' => 'your-api-v3-key',       // APIv3 密钥（可选）
        'cert_path'  => '/path/to/apiclient_cert.pem', // 商户证书路径（可选）
        'key_path'   => '/path/to/apiclient_key.pem',  // 商户证书私钥路径（可选）
        'token'      => 'your-server-token',     // 服务端消息校验 Token（可选）
        'aes_key'    => 'your-aes-key',          // 消息加解密密钥（可选）
        'cloud_env'  => 'prod-env-id',           // 云开发环境 ID（可选）
    ],
]);

$app = $kernel->wechat()->app();
```

---

## 登录认证

### 小程序登录

小程序前端调用 `wx.login()` 获取 `code`，后端用 `code` 换取 `session_key` 和 `openid`。

```php
// 小程序登录，获取 session
$session = $app->auth()->session($code);
// 返回：['openid' => 'xxx', 'session_key' => 'xxx', 'unionid' => 'xxx']

$openid     = $session['openid'];
$sessionKey = $session['session_key'];
```

### 获取 AccessToken

AccessToken 是调用微信接口的全局凭证，SDK 内部会自动缓存，一般不需要手动获取。

```php
// 获取 AccessToken（自动缓存）
$token = $app->auth()->token();
```

---

## JS-SDK

用于微信网页开发，生成 JS-SDK 配置参数供前端使用。

```php
// 生成 JS-SDK 配置
$url    = 'https://your-domain.com/page';
$apis   = ['updateAppMessageShareData', 'updateTimelineShareData', 'scanQRCode'];
$config = $app->jssdk()->config($url, $apis);

// 返回数组，直接 json_encode 给前端
// {
//   "appId": "wx123...",
//   "timestamp": 1234567890,
//   "nonceStr": "random-string",
//   "signature": "sha1-signature",
//   "jsApiList": ["updateAppMessageShareData", ...]
// }
```

---

## 用户管理

### 获取用户列表

```php
// 获取关注者列表（公众号）
$users = $app->user()->list();
// 返回：['total' => 100, 'count' => 2, 'data' => ['openid' => ['xxx', 'yyy']], 'next_openid' => '']
```

### 获取用户信息

```php
// 获取单个用户信息（需用户授权）
$userInfo = $app->user()->info($openid);
// 返回：['subscribe' => 1, 'openid' => 'xxx', 'nickname' => '张三', 'sex' => 1, ...]
```

### 设置用户备注

```php
// 给用户设置备注名（公众号）
$app->user()->remark($openid, 'VIP用户');
```

### 手机号快速验证（code 换手机号）

自基础库 **2.21.2** 起，`<button open-type="getPhoneNumber">` 回调返回的是**动态令牌 code**（不再是 `encryptedData` + `iv`），由服务端消费 code 换取手机号，**无需 `wx.login`、不依赖 `session_key`**。

```php
// 前端：e.detail.code 回传服务端
$info = $app->phone()->byCode($code);

$info['phoneNumber'];      // +8613800138000（带区号）
$info['purePhoneNumber'];  // 13800138000（不带区号）
$info['countryCode'];      // 86
$info['watermark'];        // ['timestamp' => ..., 'appid' => ...]

// 可选透传 openid（官方选填参数）
$app->phone()->byCode($code, $openid);

// 便捷方法：直接拿字符串
$app->phone()->numberByCode($code);      // '+8613800138000'
$app->phone()->pureNumberByCode($code);  // '13800138000'
```

底层调用 `POST /wxa/business/getuserphonenumber?access_token=...`，`access_token` 自动获取并复用缓存。

> 约束：每个 code 仅可消费一次、有效期 5 分钟；与 `wx.login` 的 code 不可混用（混用报 `40029`）；该能力仅对非个人主体且已认证的小程序开放并按次计费。
>
> 旧版 `encryptedData` 解密方式见 `$app->decrypt()->phone(...)`，两条路径可并行使用。统一入口见 [union.md](union.md) 的 `Union::phoneByCode()`。

---

## 素材管理

### 上传临时素材

```php
// 上传图片、语音、视频、缩略图
$media = $app->media()->upload('image', '/path/to/image.jpg');
// 返回：['type' => 'image', 'media_id' => 'xxx', 'created_at' => 1234567890]

// 上传视频
$media = $app->media()->upload('video', '/path/to/video.mp4');
```

### 上传图文消息素材

```php
// 上传图文素材（用于群发）
$app->media()->uploadNews([
    [
        'title'              => '文章标题',
        'thumb_media_id'     => $thumbMediaId,
        'author'             => '作者',
        'digest'             => '摘要',
        'show_cover_pic'     => 1,
        'content'            => '<p>文章内容 HTML</p>',
        'content_source_url' => 'https://example.com/original',
    ],
]);
```

### 删除素材

```php
// 删除永久素材
$app->media()->delete($mediaId);
```

---

## 菜单管理

### 创建自定义菜单

```php
// 创建自定义菜单（公众号）
$app->menu()->create([
    [
        'type' => 'click',
        'name' => '今日歌曲',
        'key'  => 'V1001_TODAY_MUSIC',
    ],
    [
        'type' => 'view',
        'name' => '搜索',
        'url'  => 'https://www.soso.com/',
    ],
    [
        'name'       => '菜单',
        'sub_button' => [
            ['type' => 'view', 'name' => '搜索', 'url' => 'http://www.soso.com/'],
            ['type' => 'click', 'name' => '赞一下我们', 'key' => 'V1001_GOOD'],
        ],
    ],
]);
```

### 删除菜单

```php
// 删除自定义菜单
$app->menu()->delete();
```

---

## 客服消息

### 发送客服消息

```php
// 发送文本消息
$app->customerService()->text($openid, '您好！有什么可以帮您？');

// 发送图片
$app->customerService()->image($openid, $mediaId);

// 发送图文消息
$app->customerService()->news($openid, [
    [
        'title'       => '标题',
        'description' => '描述',
        'url'         => 'https://example.com',
        'picurl'      => 'https://example.com/pic.jpg',
    ],
]);

// 发送小程序卡片
$app->customerService()->miniProgramPage(
    $openid,
    '卡片标题',
    'wxappid',
    'pages/index/index',
    $thumbMediaId
);

// 发送菜单消息
$app->customerService()->menu(
    $openid,
    '请选择服务',
    [
        ['id' => '1', 'content' => '售前咨询'],
        ['id' => '2', 'content' => '售后服务'],
    ],
    '感谢使用'
);
```

### 客服管理

```php
// 获取客服列表
$kfList = $app->customerService()->list();

// 获取客服聊天记录
$records = $app->customerService()->msgRecord(strtotime('-1 day'), time());

// 邀请客服
$app->customerService()->invite($openid, 'kf_account@gh_xxx');
```

---

## 消息推送

### 发送模板消息（公众号）

```php
// 发送模板消息
$app->message()->sendTemplate(
    $openid,
    $templateId,
    'https://example.com/order/123',  // 跳转链接
    [
        'first'    => ['value' => '订单已发货'],
        'keyword1' => ['value' => 'SF123456'],
        'keyword2' => ['value' => '2024-01-01'],
        'remark'   => ['value' => '感谢您的购买'],
    ]
);
```

### 发送订阅消息（小程序）

```php
// 发送订阅消息
$app->message()->sendSubscribe(
    $openid,
    $templateId,
    [
        'thing1' => ['value' => '商品名称'],
        'time2'  => ['value' => '2024-01-01 10:00'],
    ]
);
```

---

## 订阅消息

### 管理订阅消息模板

```php
// 获取小程序订阅消息模板列表
$templates = $app->subscribeMessage()->getTemplateList();

// 删除订阅消息模板
$app->subscribeMessage()->deleteTemplate($priTmplId);

// 发送订阅消息（同 message()->sendSubscribe）
$app->subscribeMessage()->send($openid, $templateId, $data);
```

---

## 小程序码

### 生成小程序码

```php
// 生成永久小程序码（无限制，推荐）
$qrCode = $app->miniProgramCode()->getUnlimited([
    'scene' => 'id=123&referrer=share',
    'page'  => 'pages/index/index',
    'width' => 430,
]);
file_put_contents('/tmp/qrcode.png', $qrCode);

// 生成临时小程序码
$qrCode = $app->miniProgramCode()->get([
    'path'  => 'pages/index/index?id=123',
    'width' => 430,
]);

// 生成小程序二维码（数量有限，适合少量场景）
$qrCode = $app->miniProgramCode()->getQrCode([
    'path'  => 'pages/index/index?id=123',
    'width' => 430,
]);
```

---

## 数据分析

### 获取访问留存数据

```php
// 获取每日留存
$retain = $app->dataAnalysis()->getDailyRetain('2024-01-01', '2024-01-07');

// 获取访问趋势
$trend = $app->dataAnalysis()->getDailyVisitTrend('2024-01-01', '2024-01-07');

// 获取用户画像
$portrait = $app->dataAnalysis()->getUserPortrait('2024-01-01', '2024-01-07');
```

---

## 支付

### 基础支付

```php
// 创建支付订单
$app->pay()->order([
    'description'  => '商品描述',
    'out_trade_no' => 'ORDER_001',
    'amount'       => ['total' => 100],  // 单位：分
    'payer'        => ['openid' => $openid],
]);

// 查询订单
$app->pay()->query('ORDER_001');

// 关闭订单
$app->pay()->close('ORDER_001');

// 申请退款
$app->pay()->refund([
    'out_trade_no'  => 'ORDER_001',
    'out_refund_no' => 'REFUND_001',
    'reason'        => '用户申请退款',
    'amount'        => [
        'refund'   => 100,
        'total'    => 100,
        'currency' => 'CNY',
    ],
]);

// 查询退款
$app->pay()->queryRefund('REFUND_001');

// 申请交易账单
$app->pay()->tradeBill('2024-01-01');

// 申请资金账单
$app->pay()->fundBill('2024-01-01');
```

### 企业级支付（需安装 kode/pays）

```php
// 获取企业级支付实例
$pay = $app->payBridge();
if ($pay !== null) {
    $pay->order([...]);
}
```

---

## 订单物流同步

小程序发货后需同步物流信息到微信，否则可能影响结算。

### 标准快递发货

```php
$app->shipping()->express(
    'ORDER_001',  // 商户订单号
    '1',          // 发货模式：1=标准快递
    [
        [
            'tracking_no'     => 'SF1234567890',
            'express_company' => '顺丰速运',
            'item_desc'       => '商品描述',
        ],
    ],
    $payerOpenid  // 支付者 OpenID
);
```

### 无需物流发货

```php
// 虚拟商品或服务类订单
$app->shipping()->noShipping('ORDER_001', '1', $payerOpenid);
```

### 同城配送发货

```php
$app->shipping()->sameCity('ORDER_001', '1', [
    ['tracking_no' => 'RIDER_001', 'express_company' => '同城配送', 'item_desc' => '商品描述'],
], $payerOpenid);
```

### 用户自提发货

```php
$app->shipping()->selfPickup('ORDER_001', '1', [
    ['tracking_no' => 'PICKUP_001', 'express_company' => '用户自提', 'item_desc' => '商品描述'],
], $payerOpenid);
```

### 查询发货信息

```php
$app->shipping()->getOrder('', 'ORDER_001');
```

### 确认收货提醒

```php
$app->shipping()->notifyConfirmReceive('', 'ORDER_001');
```

### 设置消息跳转路径

```php
$app->shipping()->setMsgJumpPath('pages/order/detail');
```

---

## 内容安全

### 文本检测

```php
$result = $app->security()->msgSecCheck('待检测的文本内容');
// 返回：['errcode' => 0, 'errmsg' => 'ok']
```

### 图片检测

```php
$result = $app->security()->imgSecCheck('https://example.com/image.jpg');
```

### 音视频检测

```php
$result = $app->security()->mediaCheckAsync('https://example.com/audio.mp3', 1);
// 类型：1=音频，2=视频
```

---

## URL Scheme / Link

用于在微信外（短信、邮件、浏览器）打开小程序。

### 生成 URL Scheme

```php
$scheme = $app->urlLink()->generateScheme([
    'jump_wxa' => [
        'path'  => '/pages/index/index',
        'query' => 'id=123',
    ],
]);
// 返回：['openlink' => 'weixin://dl/business/?t=xxx']
```

### 生成 URL Link

```php
$link = $app->urlLink()->generateUrlLink([
    'path'  => '/pages/index/index',
    'query' => 'id=123',
]);
// 返回：['url_link' => 'https://wxaurl.cn/xxx']
```

### 生成短链接

```php
$shortLink = $app->urlLink()->generateShortLink(
    'pages/index/index?id=123',
    '页面标题'
);
// 返回：['link' => 'https://wxaurl.cn/xxx']
```

---

## 插件管理

### 申请使用插件

```php
// 申请使用插件
$app->plugin()->applyPlugin('wx1234567890');

// 获取已添加插件列表
$plugins = $app->plugin()->list();

// 删除插件
$app->plugin()->unbindPlugin('wx1234567890');
```

---

## 直播

### 直播间管理

```php
// 创建直播间
$app->live()->createRoom([
    'name'         => '直播间名称',
    'coverImg'     => 'https://example.com/cover.jpg',
    'startTime'    => time(),
    'endTime'      => time() + 7200,
    'anchorName'   => '主播名称',
    'anchorWechat' => 'anchor_wechat',
    'type'         => 1,  // 1=推流，0=手机直播
]);

// 获取直播间列表
$liveRooms = $app->live()->getLiveInfo();

// 获取回放
$replay = $app->live()->getReplay($roomId);

// 添加商品到直播间
$app->live()->addGoods($goodsInfo);

// 商品审核
$app->live()->audit($goodsId);
```

---

## 附近小程序

### 门店管理

```php
// 添加门店
$app->nearby()->addPoi([
    'related_name'       => '门店名称',
    'related_credential' => '营业执照号',
    'related_address'    => '门店地址',
    'related_phone'      => '020-12345678',
]);

// 获取门店列表
$app->nearby()->listPoi();

// 删除门店
$app->nearby()->deletePoi($poiId);

// 设置门店状态（0=关闭，1=开启）
$app->nearby()->setStatus($poiId, 1);
```

---

## 门店小程序

### 门店管理

```php
// 创建门店
$app->store()->create([
    'name'      => '门店名称',
    'longitude' => '113.2644',
    'latitude'  => '23.1291',
    'address'   => '广州市天河区',
    'phone'     => '020-12345678',
]);

// 获取门店列表
$app->store()->list();

// 获取门店详情
$app->store()->get($poiId);

// 更新门店
$app->store()->update(['poi_id' => $poiId, 'name' => '新门店名称']);

// 删除门店
$app->store()->delete($poiId);
```

---

## 卡券

### 创建卡券

```php
$app->card()->create([
    'card_type' => 'GROUPON',
    'groupon'   => [
        'base_info'   => [
            'brand_name'  => '商家名称',
            'title'       => '团购券标题',
            'color'       => 'Color010',
            'notice'      => '使用时向服务员出示',
            'description' => '不可与其他优惠同享',
        ],
        'deal_detail' => '优惠详情说明',
    ],
]);
```

### 卡券管理

```php
// 获取卡券详情
$app->card()->get($cardId);

// 修改卡券
$app->card()->update($cardId, ['groupon' => ['base_info' => ['title' => '新标题']]]);

// 删除卡券
$app->card()->delete($cardId);

// 创建投放二维码
$app->card()->createQrcode(['action_name' => 'QR_CARD', 'action_info' => ['card' => ['card_id' => $cardId]]]);

// 核销卡券
$app->card()->consume('CODE123', $cardId);

// 查询 Code
$app->card()->getCode('CODE123', $cardId);

// 批量查询卡券列表
$app->card()->list(0, 50);

// 设置卡券失效
$app->card()->unavailable('CODE123', $cardId, '用户退款');
```

---

## 摇一摇

### 设备管理

```php
// 申请设备 ID
$app->shake()->applyDeviceId(10);

// 查询设备列表
$app->shake()->deviceList($applyId);

// 配置设备与页面关联
$app->shake()->bindPage(
    [['device_id' => 123, 'uuid' => 'xxx', 'major' => 1, 'minor' => 1]],
    [['page_id' => 1]]
);
```

### 页面管理

```php
// 新增页面
$app->shake()->addPage([
    'title'       => '页面标题',
    'description' => '页面描述',
    'page_url'    => 'https://example.com',
    'comment'     => '备注',
]);

// 查询页面列表
$app->shake()->pageList();

// 删除页面
$app->shake()->deletePage([1, 2, 3]);
```

### 获取摇一摇信息

```php
// 获取摇周边的设备及用户信息
$app->shake()->getShakeInfo($ticket);
```

### 数据统计

```php
// 获取页面统计数据
$app->shake()->statistics($pageId, 1704067200, 1706659200);
```

---

## 发票

### 获取授权页链接

```php
$app->invoice()->getAuthUrl([
    's_pappid' => 'wx123',
    'order_id' => 'ORDER001',
    'money'    => 100,
    'timestamp'=> time(),
    'source'   => 'web',
]);
```

### 开具发票

```php
$app->invoice()->makeOutInvoice([
    'wxopenid' => $openid,
    'order_id' => 'ORDER001',
    'card_id'  => $cardId,
    'card_ext' => '{}',
]);
```

### 查询发票

```php
// 查询单张发票
$app->invoice()->queryInvoiceInfo($cardId, $encryptCode);

// 批量查询发票
$app->invoice()->queryInvoiceBatch([
    ['card_id' => $cardId, 'encrypt_code' => $encryptCode],
]);
```

### 更新发票状态

```php
// 报销状态：INVOICE_REIMBURSE_INIT=未报销，INVOICE_REIMBURSE_PROCESS=报销中，INVOICE_REIMBURSE_FINISH=已报销
$app->invoice()->updateStatus($cardId, $encryptCode, 'INVOICE_REIMBURSE_FINISH');
```

---

## 连 Wi-Fi

### 设备管理

```php
// 添加密码型设备
$app->wifi()->addDevice([
    'shop_id'  => 123,
    'ssid'     => 'MyWiFi',
    'password' => 'password123',
]);

// 查询设备列表
$app->wifi()->deviceList();

// 删除设备
$app->wifi()->deleteDevice('00:11:22:33:44:55');
```

### 获取二维码

```php
$app->wifi()->getQrcode(123, 'MyWiFi');
```

### 商家主页

```php
// 设置商家主页
$app->wifi()->setHomePage(123, [
    'bar_type' => 1,
    'link_url' => 'https://example.com',
]);

// 查询商家主页
$app->wifi()->getHomePage(123);
```

### 统计

```php
$app->wifi()->statistics(123, '2024-01-01', '2024-01-31');
```

---

## 微信小店

微信小店（视频号电商）商品和订单管理。

### 商品管理

```php
// 添加商品
$app->goods()->add([
    'title'       => '商品标题',
    'head_imgs'   => ['https://example.com/img.jpg'],
    'category_id' => 100,
]);

// 获取商品列表
$app->goods()->list();

// 获取商品详情
$app->goods()->get($productId);

// 更新商品
$app->goods()->update(['product_id' => $productId, 'title' => '新标题']);

// 删除商品
$app->goods()->delete($productId);

// 上架商品
$app->goods()->listing($productId);

// 下架商品
$app->goods()->delisting($productId);
```

### 订单管理

```php
// 获取订单列表
$app->goods()->orderList();

// 获取订单详情
$app->goods()->orderGet($orderId);
```

---

## 红包

### 发送普通红包

```php
$app->redpack()->send([
    'send_name'    => '商家名称',
    're_openid'    => $openid,
    'total_amount' => 100,  // 单位：分
    'total_num'    => 1,
    'wishing'      => '恭喜发财',
    'act_name'     => '活动名称',
    'remark'       => '备注',
]);
```

### 发送裂变红包

```php
$app->redpack()->sendGroup([
    'send_name'    => '商家名称',
    're_openid'    => $openid,
    'total_amount' => 100,
    'total_num'    => 3,
    'wishing'      => '恭喜发财',
    'act_name'     => '活动名称',
    'remark'       => '备注',
]);
```

### 查询红包记录

```php
$app->redpack()->query('MCHBILLNO001');
```

---

## 广告

### 广告单元管理

```php
// 创建广告单元
$app->ad()->createAdUnit([
    'ad_unit_name' => '广告单元1',
    'ad_unit_type' => 1,
]);

// 获取广告单元列表
$app->ad()->adUnitList();

// 获取广告数据
$app->ad()->getData($adUnitId, '2024-01-01', '2024-01-31');
```

---

## 即时配送

### 配送公司管理

```php
// 获取已支持的配送公司列表
$app->express()->deliveryList();
```

### 订单管理

```php
// 预下配送单
$app->express()->preAddOrder([
    'shopid'        => 'SHOP001',
    'shop_order_id' => 'ORDER001',
    'delivery_id'   => 1,
    'openid'        => $openid,
    'sender'        => ['name' => '商家', 'city' => '广州市', 'address' => '天河区'],
    'receiver'      => ['name' => '用户', 'city' => '广州市', 'address' => '海珠区'],
    'cargo'         => ['goods_name' => '商品', 'goods_value' => 100],
    'order_info'    => ['delivery_time' => time() + 1800],
]);

// 下配送单
$app->express()->addOrder([...]);

// 重新下配送单
$app->express()->reOrder([...]);

// 取消配送单
$app->express()->cancelOrder([
    'shopid'        => 'SHOP001',
    'shop_order_id' => 'ORDER001',
    'delivery_id'   => 1,
    'waybill_id'    => 'WB001',
]);

// 查询配送单
$app->express()->getOrder([
    'shopid'        => 'SHOP001',
    'shop_order_id' => 'ORDER001',
    'delivery_id'   => 1,
]);
```

---

## 搜一搜

### 页面收录

```php
// 提交小程序页面供微信搜一搜收录
$app->search()->submitPages([
    'pages/index/index',
    'pages/detail/detail',
    'pages/list/list',
]);
```

### 数据统计

```php
// 获取搜一搜数据统计
$app->search()->getData('2024-01-01', '2024-01-31');
```

---

## 动态消息

用于小程序被分享后，接收者打开前动态更新消息内容（如拼团进度、游戏状态）。

```php
// 创建活动 ID
$result = $app->dynamicMessage()->createActivityId();
$activityId = $result['activity_id'];

// 更新动态消息内容
$app->dynamicMessage()->setUpdatableMsg([
    'activity_id'   => $activityId,
    'target_state'  => 0,  // 0=未开始，1=进行中
    'template_info' => [
        'parameter_list' => [
            ['name' => 'member_count', 'value' => '3'],
            ['name' => 'room_limit', 'value' => '5'],
        ],
    ],
]);
```

---

## 设备功能

用于智能硬件与微信绑定。

### 设备授权

```php
// 获取设备二维码
$app->device()->getQrcode([
    [
        'id'                  => 'DEVICE001',
        'mac'                 => '00:11:22:33:44:55',
        'connect_protocol'    => '3',  // 3=BLE
        'auth_key'            => '',
        'close_strategy'      => '1',
        'conn_strategy'       => '1',
        'crypt_method'        => '0',
        'auth_ver'            => '0',
        'manu_mac_pos'        => '-1',
        'ser_mac_pos'         => '-2',
        'ble_simple_protocol' => '0',
    ],
]);

// 授权设备
$app->device()->authorize([...]);

// 查询设备状态
$app->device()->getStat('DEVICE001');
```

### 绑定管理

```php
// 绑定用户和设备
$app->device()->bind($ticket, $deviceId, $openid);

// 解绑用户和设备
$app->device()->unbind($ticket, $deviceId, $openid);

// 强制绑定
$app->device()->compelBind($deviceId, $openid);

// 强制解绑
$app->device()->compelUnbind($deviceId, $openid);
```

---

## 云开发

服务端调用云开发能力，无需前端参与。

### 云函数

```php
// 调用云函数
$app->cloudbase()->invokeFunction('myFunction', ['key' => 'value']);
```

### 数据库

```php
// 查询数据
$app->cloudbase()->databaseQuery([
    'env'   => 'prod-env-id',
    'query' => 'db.collection("users").where({age: _.gt(18)}).get()',
]);

// 添加数据
$app->cloudbase()->databaseAdd([
    'env'   => 'prod-env-id',
    'query' => 'db.collection("users").add({data:{name:"张三",age:20}})',
]);

// 更新数据
$app->cloudbase()->databaseUpdate([
    'env'   => 'prod-env-id',
    'query' => 'db.collection("users").doc("xxx").update({data:{age:21}})',
]);

// 删除数据
$app->cloudbase()->databaseDelete([
    'env'   => 'prod-env-id',
    'query' => 'db.collection("users").doc("xxx").remove()',
]);
```

### 文件存储

```php
// 获取文件上传链接
$app->cloudbase()->uploadFile('/path/to/file.jpg');

// 批量获取文件下载链接
$app->cloudbase()->batchDownloadFile([
    'cloud://prod-env-id/path/to/file1.jpg',
    'cloud://prod-env-id/path/to/file2.jpg',
]);
```

---

## 服务端消息处理

处理微信推送的消息和事件。

```php
use Kode\MiniApp\Server\Message;

$server = $app->server();

// 处理文本消息
$server->on('text', function (array $payload, $app) {
    $content = $payload['Content'];
    return Message::toXml(Message::text('收到：' . $content, $payload));
});

// 处理关注事件
$server->on('subscribe', function (array $payload, $app) {
    return Message::toXml(Message::text('感谢关注！回复「帮助」查看使用指南。', $payload));
});

// 处理取消关注
$server->on('unsubscribe', function (array $payload, $app) {
    // 记录取消关注日志
    return 'success';
});

// 处理点击菜单事件
$server->on('CLICK', function (array $payload, $app) {
    $key = $payload['EventKey'];
    return Message::toXml(Message::text('您点击了：' . $key, $payload));
});

// 处理扫码事件
$server->on('SCAN', function (array $payload, $app) {
    $scene = $payload['EventKey'];
    return Message::toXml(Message::text('扫码参数：' . $scene, $payload));
});

// 处理图片消息
$server->on('image', function (array $payload, $app) {
    $picUrl = $payload['PicUrl'];
    return Message::toXml(Message::text('收到图片：' . $picUrl, $payload));
});

// 处理语音消息
$server->on('voice', function (array $payload, $app) {
    $mediaId = $payload['MediaId'];
    return Message::toXml(Message::voice($mediaId, $payload));
});

// 处理视频消息
$server->on('video', function (array $payload, $app) {
    return Message::toXml(Message::text('收到视频消息', $payload));
});

// 处理地理位置消息
$server->on('location', function (array $payload, $app) {
    $location = $payload['Location_X'] . ',' . $payload['Location_Y'];
    return Message::toXml(Message::text('您的位置：' . $location, $payload));
});

// 处理链接消息
$server->on('link', function (array $payload, $app) {
    return Message::toXml(Message::text('收到链接：' . $payload['Url'], $payload));
});

// 启动服务
$response = $server->serve();
$response->send();
```

---

## 支付回调通知

处理微信支付结果通知。

```php
$notify = $app->notify();

$result = $notify
    ->onPaid(function (array $payload, $app) {
        // 支付成功处理
        $outTradeNo = $payload['out_trade_no'];
        $totalFee   = $payload['total_fee'];

        // TODO: 更新订单状态为已支付
        // TODO: 发货或开通服务
        // TODO: 记录支付日志

        // 返回 true 表示处理成功，false 表示处理失败（微信会重试）
        return true;
    })
    ->onRefund(function (array $payload, $app) {
        // 退款成功处理
        $outRefundNo = $payload['out_refund_no'];

        // TODO: 更新退款状态

        return true;
    })
    ->handle();

// 返回给微信
if ($result['code'] === 'SUCCESS') {
    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
} else {
    echo '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[' . $result['message'] . ']]></return_msg></xml>';
}
```

---

## 更多参考

- [微信支付官方文档](https://pay.weixin.qq.com/wiki/doc/apiv3/index.shtml)
- [微信小程序官方文档](https://developers.weixin.qq.com/miniprogram/dev/framework/)
- [微信公众号官方文档](https://developers.weixin.qq.com/doc/offiaccount/Getting_Started/Overview.html)
