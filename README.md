# Kode MiniApp

多平台小程序、公众号、企业号统一接入 SDK。

[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)

## 特性

- **多平台统一接入**：一套代码对接微信、支付宝、抖音、百度、QQ、企业微信、钉钉、飞书
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
| 微信 | `wechat` | C端 | 登录、JS-SDK、用户、素材、菜单、客服、消息、订阅消息、小程序码、数据分析、支付、订单物流同步、内容安全、URL Scheme/Link、插件管理、直播、附近小程序、服务端、回调通知 |
| 支付宝 | `alipay` | C端 | 登录、支付、转账、账单、回调通知 |
| 抖音 | `douyin` | C端 | 登录、支付 |
| 百度 | `baidu` | C端 | 登录、支付 |
| QQ | `qq` | C端 | 登录 |
| 微信企业号 | `wechat_work` | B端 | 认证、通讯录、部门管理、客户联系、外部联系人、标签、消息、审批、素材管理、应用管理、OA打卡汇报、会议室、公费电话、服务端、回调通知 |
| 钉钉 | `dingtalk` | B端 | 认证、通讯录、消息、审批、群机器人、考勤、智能人事、日志 |
| 飞书 | `lark` | B端 | 认证、通讯录、消息、审批、多维表格、文档、日历、任务、知识库 |

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
- **Server**：服务端消息处理器，统一处理各平台的消息推送和事件回调
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
$app->customerService()->news($openid, [['title' => '标题', 'description' => '描述', 'url' => 'https://example.com', 'picurl' => 'https://example.com/pic.jpg']]);
$app->customerService()->miniProgramPage($openid, '标题', $appid, 'pages/index/index', $thumbMediaId);
$app->customerService()->menu($openid, '请选择', [['id' => '1', 'content' => '选项1']], '感谢使用');

// 客服管理
$kfList = $app->customerService()->list();
$records = $app->customerService()->msgRecord(strtotime('-1 day'), time());
$app->customerService()->invite($openid, 'kf_account@gh_xxx');

// 发送订阅消息（小程序）
$app->message()->sendSubscribe($openid, $templateId, ['thing1' => ['value' => '测试']]);

// 发送模板消息（公众号）
$app->message()->sendTemplate($openid, $templateId, '', ['thing1' => ['value' => '测试']]);

// 小程序码生成
$qrCode = $app->miniProgramCode()->getUnlimited(['scene' => 'id=123', 'page' => 'pages/index/index']);
file_put_contents('/tmp/qrcode.png', $qrCode);

// 数据分析
$retain = $app->dataAnalysis()->getDailyRetain('2024-01-01', '2024-01-07');
$trend  = $app->dataAnalysis()->getDailyVisitTrend('2024-01-01', '2024-01-07');
$portrait = $app->dataAnalysis()->getUserPortrait('2024-01-01', '2024-01-07');

// 订阅消息管理（小程序）
$app->subscribeMessage()->send($openid, $templateId, ['thing1' => ['value' => '测试']]);
$templates = $app->subscribeMessage()->getTemplateList();
$app->subscribeMessage()->deleteTemplate($priTmplId);

// 基础支付
$app->pay()->order([
    'description'  => '商品描述',
    'out_trade_no' => 'ORDER_001',
    'amount'       => ['total' => 100],
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

// 申请账单
$app->pay()->tradeBill('2024-01-01');
$app->pay()->fundBill('2024-01-01');

// 小程序订单物流同步（发货）
// 标准快递发货
$app->shipping()->express(
    'ORDER_001',
    '1',
    [
        [
            'tracking_no'   => 'SF1234567890',
            'express_company' => '顺丰速运',
            'item_desc'     => '商品描述',
        ],
    ],
    $payerOpenid
);

// 无需物流发货（虚拟商品/服务）
$app->shipping()->noShipping('ORDER_001', '1', $payerOpenid);

// 同城配送发货
$app->shipping()->sameCity('ORDER_001', '1', [
    ['tracking_no' => 'RIDER_001', 'express_company' => '同城配送', 'item_desc' => '商品描述'],
], $payerOpenid);

// 用户自提发货
$app->shipping()->selfPickup('ORDER_001', '1', [
    ['tracking_no' => 'PICKUP_001', 'express_company' => '用户自提', 'item_desc' => '商品描述'],
], $payerOpenid);

// 查询发货信息
$app->shipping()->getOrder('', 'ORDER_001');

// 确认收货提醒
$app->shipping()->notifyConfirmReceive('', 'ORDER_001');

// 设置消息跳转路径
$app->shipping()->setMsgJumpPath('pages/order/detail');

// 内容安全检测
$result = $app->security()->msgSecCheck('待检测文本内容');
$result = $app->security()->imgSecCheck('https://example.com/image.jpg');
$result = $app->security()->mediaCheckAsync('https://example.com/audio.mp3', 1);

// URL Scheme / URL Link（短信、邮件、微信外打开小程序）
$scheme = $app->urlLink()->generateScheme([
    'jump_wxa' => ['path' => '/pages/index/index', 'query' => 'id=123'],
]);
$link = $app->urlLink()->generateUrlLink([
    'path'  => '/pages/index/index',
    'query' => 'id=123',
]);
$shortLink = $app->urlLink()->generateShortLink('pages/index/index?id=123', '页面标题');

// 插件管理
$app->plugin()->applyPlugin('wx1234567890');
$plugins = $app->plugin()->list();
$app->plugin()->unbindPlugin('wx1234567890');

// 小程序直播
$app->live()->createRoom([
    'name'         => '直播间名称',
    'coverImg'     => 'https://example.com/cover.jpg',
    'startTime'    => time(),
    'endTime'      => time() + 7200,
    'anchorName'   => '主播名称',
    'anchorWechat' => 'anchor_wechat',
    'type'         => 1,
]);
$liveRooms = $app->live()->getLiveInfo();
$replay = $app->live()->getReplay($roomId);
$app->live()->addGoods($goodsInfo);
$app->live()->audit($goodsId);

// 附近小程序
$app->nearby()->addPoi(['related_name' => '门店名称', 'related_credential' => '营业执照号', 'related_address' => '地址', 'related_phone' => '电话']);
$app->nearby()->listPoi();
$app->nearby()->deletePoi($poiId);
$app->nearby()->setStatus($poiId, 1);

// 企业级支付（需安装 kode/pays）
$pay = $app->payBridge();
if ($pay !== null) {
    $pay->order([...]);
}
```

### 微信服务端消息处理

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

// 部门管理
$app->department()->create(['name' => '技术部', 'parentid' => 1]);
$departments = $app->department()->list();
$app->department()->update(['id' => 2, 'name' => '产品部']);
$app->department()->delete(2);

// 客户联系（外部联系人）
$followUsers = $app->externalContact()->getFollowUserList();
$customers = $app->externalContact()->list('zhangsan');
$detail = $app->externalContact()->get($externalUserid);
$app->externalContact()->addContactWay([
    'type' => 1,
    'scene' => 1,
    'style' => 1,
    'remark' => '渠道客户',
]);
$app->externalContact()->remark([
    'userid' => 'zhangsan',
    'external_userid' => $externalUserid,
    'remark' => 'VIP客户',
]);

// 客户群管理
$groups = $app->externalContact()->groupChatList();
$groupDetail = $app->externalContact()->groupChatGet('chat_id');

// 离职继承
$unassigned = $app->externalContact()->getUnassignedList();
$app->externalContact()->transfer([
    'external_userid' => $externalUserid,
    'handover_userid' => 'zhangsan',
    'takeover_userid' => 'lisi',
]);

// 客户标签
$tags = $app->externalContact()->getCorpTagList();
$app->externalContact()->addCorpTag([
    'group_name' => '客户等级',
    'tag' => [['name' => 'VIP']],
]);

// 素材管理
$media = $app->media()->upload('image', '/path/to/image.jpg');
$app->media()->uploadImg('/path/to/image.jpg');
$app->media()->uploadAttachment('image', '/path/to/image.jpg', 'image');

// 应用管理
$app->agent()->get(1000002);
$app->agent()->list();
$app->agent()->set(['agentid' => 1000002, 'report_location_flag' => 0]);

// 消息推送
$app->message()->text('Hello World', ['zhangsan']);
$app->message()->markdown('# 标题\n内容', ['zhangsan']);
$app->message()->news([['title' => '标题', 'description' => '描述', 'url' => 'https://example.com', 'picurl' => 'https://example.com/pic.jpg']], ['zhangsan']);
$app->message()->file($mediaId, ['zhangsan']);
$app->message()->image($mediaId, ['zhangsan']);
$app->message()->voice($mediaId, ['zhangsan']);
$app->message()->video($mediaId, '标题', '描述', ['zhangsan']);
$app->message()->textCard('标题', '描述内容', 'https://example.com', ['zhangsan'], '查看详情');
$app->message()->miniProgramNotice([
    'appid'             => 'wx123',
    'page'              => 'pages/index',
    'title'             => '通知标题',
    'description'       => '通知内容',
    'emphasis_first_item' => true,
    'content_item'      => [['key' => '订单号', 'value' => '123456']],
], ['zhangsan']);

// 审批
$app->approval()->template($templateId);
$app->approval()->apply($approvalData);
$app->approval()->detail($spNo);

// OA 打卡汇报
$app->oa()->getCheckinOption(time(), ['zhangsan']);
$app->oa()->getCheckinData(strtotime('-7 days'), time(), ['zhangsan']);
$app->oa()->getCheckinDayData(strtotime('-7 days'), time(), ['zhangsan']);
$app->oa()->getCheckinMonthData(strtotime('-30 days'), time(), ['zhangsan']);
$app->oa()->getJournalRecordList(strtotime('-7 days'), time());
$app->oa()->getJournalStat(strtotime('-7 days'), time());

// 会议室管理
$app->meeting()->create(['name' => '会议室A', 'capacity' => 10, 'city' => '深圳']);
$app->meeting()->list();
$app->meeting()->getBookingInfo($meetingRoomId, '2024-01-01T09:00:00', '2024-01-01T18:00:00');
$app->meeting()->book(['meetingroom_id' => $meetingRoomId, 'subject' => '周会', 'start_time' => time(), 'end_time' => time() + 3600, 'booker' => 'zhangsan']);
$app->meeting()->cancelBook($meetingId);

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
$app->message()->image($mediaId, ['zhangsan']);
$app->message()->file($mediaId, ['zhangsan']);
$app->message()->link('标题', '内容', 'https://example.com', 'https://example.com/pic.jpg', ['zhangsan']);
$app->message()->oa($oaContent, ['zhangsan']);
$app->message()->actionCard([
    'title'          => '标题',
    'markdown'       => '内容',
    'single_title'   => '查看详情',
    'single_url'     => 'https://example.com',
], ['zhangsan']);

// 审批
$app->approval()->instance($processInstanceId);
$app->approval()->create($data);

// 群机器人消息
$app->robot()->text($webhook, $secret, 'Hello 钉钉');
$app->robot()->markdown($webhook, $secret, '标题', '**加粗内容**');
$app->robot()->link($webhook, $secret, '标题', '内容', 'https://example.com');
$app->robot()->actionCard($webhook, $secret, [
    'title' => '标题',
    'markdown' => '内容',
    'singleTitle' => '查看详情',
    'singleURL' => 'https://example.com',
]);

// 考勤管理
$app->attendance()->list('2024-01-01 00:00:00', '2024-01-31 23:59:59', ['zhangsan']);
$app->attendance()->listSchedule(['zhangsan'], '2024-01-01');
$app->attendance()->getGroup(1);
$app->attendance()->getRecord('2024-01-01 00:00:00', '2024-01-31 23:59:59', ['zhangsan']);

// 智能人事
$app->hrm()->getEmpRosterDetail(['zhangsan']);
$app->hrm()->queryOnJob();
$app->hrm()->queryPreEntry();
$app->hrm()->queryDimission();
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
$app->message()->post('ou_xxx', ['zh_cn' => ['title' => '标题', 'content' => [['tag' => 'text', 'text' => '内容']]]]);
$app->message()->image('ou_xxx', $imageKey);
$app->message()->file('ou_xxx', $fileKey);
$app->message()->interactive('ou_xxx', ['config' => ['wide_screen_mode' => true], 'elements' => []]);

// 审批
$app->approval()->create($data);
$app->approval()->instance($instanceCode);

// 多维表格
$app->bitable()->meta($appToken);
$app->bitable()->tables($appToken);
$app->bitable()->createRecord($appToken, $tableId, ['字段名' => '值']);
$app->bitable()->records($appToken, $tableId);
$app->bitable()->updateRecord($appToken, $tableId, $recordId, ['字段名' => '新值']);
$app->bitable()->deleteRecord($appToken, $tableId, $recordId);

// 文档管理
$doc = $app->doc()->create('新文档标题', $folderToken);
$app->doc()->meta($documentId);
$app->doc()->rawContent($documentId);
$app->doc()->blocks($documentId, $blockId);
$app->doc()->createBlock($documentId, $blockId, [
    ['block_type' => 2, 'text' => ['elements' => [['text_run' => ['content' => 'Hello World']]]]],
]);

// 日历管理
$app->calendar()->create(['summary' => '团队日历', 'description' => '用于团队协作']);
$app->calendar()->list();
$app->calendar()->get($calendarId);
$app->calendar()->delete($calendarId);
$app->calendar()->createEvent($calendarId, [
    'summary'     => '周会',
    'start'       => ['date_time' => '2024-01-01T10:00:00+08:00'],
    'end'         => ['date_time' => '2024-01-01T11:00:00+08:00'],
    'attendees'   => [['user_id' => 'ou_xxx']],
]);
$app->calendar()->listEvents($calendarId);
$app->calendar()->getEvent($calendarId, $eventId);
$app->calendar()->deleteEvent($calendarId, $eventId);

// 任务管理
$task = $app->task()->create(['summary' => '完成需求文档', 'due' => ['date' => '2024-01-15']]);
$app->task()->get($taskGuid);
$app->task()->update($taskGuid, ['summary' => '更新后的任务标题']);
$app->task()->complete($taskGuid);
$app->task()->uncomplete($taskGuid);
$app->task()->delete($taskGuid);
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
# 升级 patch 版本（1.1.0 -> 1.1.1）
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

## 许可证

Apache-2.0 License
