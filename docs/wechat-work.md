# 企业微信使用文档

> 对应平台标识：`wechat_work`
>
> 适用场景：企业内部应用、第三方应用、客户联系

---

## 目录

1. [配置说明](#配置说明)
2. [登录认证](#登录认证)
3. [通讯录管理](#通讯录管理)
4. [部门管理](#部门管理)
5. [客户联系](#客户联系)
6. [外部联系人](#外部联系人)
7. [标签管理](#标签管理)
8. [消息推送](#消息推送)
9. [审批](#审批)
10. [素材管理](#素材管理)
11. [应用管理](#应用管理)
12. [OA 打卡汇报](#oa-打卡汇报)
13. [会议室](#会议室)
14. [公费电话](#公费电话)
15. [日程管理](#日程管理)
16. [收集表](#收集表)
17. [微盘](#微盘)
18. [上下游/互联企业](#上下游互联企业)
19. [会话内容存档](#会话内容存档)
20. [服务端消息处理](#服务端消息处理)
21. [支付回调通知](#支付回调通知)

---

## 配置说明

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'wechat_work' => [
        'corp_id'      => 'wwxxxxxxxxxxxxxxxx',  // 企业 CorpID
        'app_id'       => 'wwappxxxxxxxxxxxxxx',  // 小程序 AppID（客户端敏感数据 watermark.appid 校验必填）
        'secret'       => 'your-app-secret',      // 应用 Secret
        'agent_id'     => 1000002,                // 应用 AgentID
        'token'        => 'your-server-token',    // 服务端消息校验 Token（可选）
        'aes_key'      => 'your-aes-key',         // 消息加解密密钥（可选）
        'mch_id'       => '1234567890',           // 支付商户号（可选）
        'api_v3_key'   => 'your-api-v3-key',      // 支付 APIv3 密钥（可选）
    ],
]);

$app = $kernel->wechatWork()->app();
```

---

## 登录认证

### 获取 AccessToken

```php
// 获取 AccessToken（自动缓存）
$token = $app->auth()->token();
```

### 获取登录用户信息

```php
// 用登录码换取用户信息（自建应用）
$user = $app->auth()->getUserInfo($code);
// 返回：['userid' => 'zhangsan', 'user_ticket' => 'xxx']

// 获取用户详情
$detail = $app->auth()->getUserDetail($user['user_ticket']);
// 返回：['userid' => 'zhangsan', 'name' => '张三', 'department' => [1, 2], ...]
```

---

## 通讯录管理

### 成员管理

```php
// 创建成员
$app->contact()->createUser([
    'userid'     => 'zhangsan',
    'name'       => '张三',
    'department' => [1, 2],
    'mobile'     => '13800138000',
    'email'      => 'zhangsan@example.com',
]);

// 获取成员详情
$app->contact()->getUser('zhangsan');

// 更新成员
$app->contact()->updateUser([
    'userid' => 'zhangsan',
    'name'   => '张三丰',
]);

// 删除成员
$app->contact()->deleteUser('zhangsan');

// 批量获取成员
$app->contact()->getUserListId([
    'userid' => ['zhangsan', 'lisi'],
]);

// 获取部门成员
$app->contact()->getDepartmentUserList(1, 1);
// 参数：department_id, fetch_child(1=递归)

// 获取部门成员详情
$app->contact()->getDepartmentUserDetailList(1, 1);
```

### 邀请成员

```php
$app->contact()->batchInvite([
    'user'  => ['zhangsan', 'lisi'],
    'party' => [1, 2],
    'tag'   => [1],
]);
```

---

## 部门管理

```php
// 创建部门
$app->department()->create([
    'name'     => '技术部',
    'parentid' => 1,
    'order'    => 1,
]);

// 获取部门列表
$app->department()->list();

// 获取部门详情
$app->department()->get(2);

// 更新部门
$app->department()->update([
    'id'   => 2,
    'name' => '研发中心',
]);

// 删除部门
$app->department()->delete(2);
```

---

## 客户联系

### 客户管理

```php
// 获取客户列表
$app->customer()->getFollowUserList();

// 获取客户详情
$app->customer()->getExternalContact('external_userid');

// 获取客户群列表
$app->customer()->getGroupChatList();

// 获取客户群详情
$app->customer()->getGroupChatDetail('chat_id');

// 配置客户群进群方式
$app->customer()->groupChatJoinWay([
    'scene'          => 1,
    'remark'         => '客户群',
    'auto_create_room' => true,
    'room_base_name'   => 'VIP客户群',
]);
```

### 客户标签

```php
// 获取企业标签库
$app->customer()->getCorpTagList();

// 添加企业客户标签
$app->customer()->addCorpTag([
    'group_id' => 'GROUP001',
    'tag'      => [['name' => '重要客户']],
]);

// 编辑企业客户标签
$app->customer()->editCorpTag([
    'id'   => 'TAG001',
    'name' => 'VIP客户',
]);

// 删除企业客户标签
$app->customer()->delCorpTag(['TAG001']);

// 编辑客户企业标签
$app->customer()->markTag([
    'userid'          => 'zhangsan',
    'external_userid' => 'woxxxxxxxx',
    'add_tag'         => ['TAG001'],
]);
```

---

## 外部联系人

### 离职继承

```php
// 分配离职成员的客户
$app->externalContact()->transferCustomer([
    'handover_userid' => 'zhangsan',
    'takeover_userid' => 'lisi',
    'external_userid' => ['woxxxxxxxx'],
]);

// 分配离职成员的客户群
$app->externalContact()->transferGroupChat([
    'chat_id_list'    => ['CHAT001'],
    'new_owner'       => 'lisi',
]);
```

---

## 标签管理

```php
// 创建标签
$app->tag()->create('标签名称', 1);  // 参数：name, tag_id（可选）

// 更新标签
$app->tag()->update(1, '新标签名称');

// 删除标签
$app->tag()->delete(1);

// 获取标签成员
$app->tag()->get(1);

// 增加标签成员
$app->tag()->addUsers(1, ['zhangsan', 'lisi'], []);

// 删除标签成员
$app->tag()->removeUsers(1, ['zhangsan'], []);

// 获取标签列表
$app->tag()->list();
```

---

## 消息推送

### 发送应用消息

```php
// 发送文本消息
$app->message()->sendText('zhangsan', '您好！');

// 发送图文消息
$app->message()->sendNews('zhangsan', [
    [
        'title'       => '标题',
        'description' => '描述',
        'url'         => 'https://example.com',
        'picurl'      => 'https://example.com/pic.jpg',
    ],
]);

// 发送 Markdown
$app->message()->sendMarkdown('zhangsan', "# 标题\n\n**加粗内容**");

// 发送卡片
$app->message()->sendTemplateCard('zhangsan', [
    'card_type' => 'text_notice',
    'source'    => ['desc' => '企业微信'],
    'main_title'=> ['title' => '卡片标题', 'desc' => '卡片描述'],
]);

// 发送文件
$app->message()->sendFile('zhangsan', $mediaId);

// 发送图片
$app->message()->sendImage('zhangsan', $mediaId);

// 发送语音
$app->message()->sendVoice('zhangsan', $mediaId);

// 发送视频
$app->message()->sendVideo('zhangsan', $mediaId);

// 发送小程序通知消息
$app->message()->sendMiniProgramNotice('zhangsan', [
    'appid'             => 'wx123',
    'page'              => 'pages/index',
    'title'             => '标题',
    'description'       => '描述',
    'emphasis_first_item'=> true,
    'content_item'      => [
        ['key' => '订单号', 'value' => '123456'],
        ['key' => '金额', 'value' => '100元'],
    ],
]);
```

### 更新任务卡片

```php
$app->message()->updateTaskCard('zhangsan', [
    'response_code' => 'RESP001',
    'button'        => ['replace_name' => '已处理'],
]);
```

---

## 审批

### 审批申请

```php
// 提交审批申请
$app->approval()->submit([
    'creator_userid'       => 'zhangsan',
    'template_id'          => 'TEMPLATE001',
    'use_template_approver'=> 1,
    'approver'             => [
        [
            'attr'   => 1,
            'userid' => ['lisi'],
        ],
    ],
    'notify_type'          => 1,
    'notify_userid'        => ['wangwu'],
    'apply_data'           => [
        'contents' => [
            ['control' => 'Text', 'id' => 'Text-123', 'value' => ['text' => '请假事由']],
        ],
    ],
    'summary_list'         => [
        ['summary_info' => [{'text' => '请假申请', 'lang' => 'zh_CN'}]],
    ],
]);
```

### 审批查询

```php
// 查询审批申请状态
$app->approval()->getDetail('APPROVAL001');

// 获取审批模板详情
$app->approval()->getTemplateDetail('TEMPLATE001');

// 批量获取审批单号
$app->approval()->getApprovalInfo(1704067200, 1706659200);
```

---

## 素材管理

```php
// 上传临时素材
$media = $app->media()->upload('image', '/path/to/image.jpg');

// 上传图片
$media = $app->media()->uploadImage('/path/to/image.jpg');

// 上传临时素材（multipart）
$media = $app->media()->uploadMedia('image', '/path/to/image.jpg');
```

---

## 应用管理

```php
// 获取应用详情
$app->agent()->getAgentInfo();

// 设置应用
$app->agent()->setAgent([
    'agentid'            => 1000002,
    'report_location_flag'=> 0,
    'logo_mediaid'       => $mediaId,
    'name'               => '应用名称',
    'description'        => '应用描述',
    'redirect_domain'    => 'example.com',
    'isreportenter'      => 0,
    'home_url'           => 'https://example.com',
]);

// 获取应用列表
$app->agent()->getAgentList();
```

---

## OA 打卡汇报

### 打卡

```php
// 获取打卡规则
$app->oa()->getCheckInOption(1704067200, ['zhangsan']);

// 获取打卡数据
$app->oa()->getCheckInData(1704067200, 1706659200, ['zhangsan']);

// 获取打卡日报
$app->oa()->getCheckInDayData(1704067200, 1706659200, ['zhangsan']);

// 获取打卡月报
$app->oa()->getCheckInMonthData(1704067200, 1706659200, ['zhangsan']);

// 获取审批中的打卡
$app->oa()->getCheckInScheduleList(1704067200, 1706659200, ['zhangsan']);
```

### 汇报

```php
// 获取汇报记录
$app->oa()->getJournalRecordList(1704067200, 1706659200, 0);
```

---

## 会议室

```php
// 创建会议室
$app->meeting()->create([
    'name'     => '会议室A',
    'capacity' => 10,
    'city'     => '深圳',
    'building' => 'A栋',
    'floor'    => '3F',
    'equipment'=> [1, 2, 3],
]);

// 获取会议室列表
$app->meeting()->list();

// 查询会议室预定信息
$app->meeting()->getBookingInfo($meetingRoomId, '2024-01-01T09:00:00', '2024-01-01T18:00:00');

// 预定会议室
$app->meeting()->book([
    'meetingroom_id' => $meetingRoomId,
    'subject'        => '周会',
    'start_time'     => time(),
    'end_time'       => time() + 3600,
    'booker'         => 'zhangsan',
    'attendees'      => ['lisi', 'wangwu'],
]);

// 取消预定
$app->meeting()->cancelBook($meetingId);
```

---

## 公费电话

```php
// 拨打电话
$app->dial()->call(['zhangsan', 'lisi'], 'wangwu', '项目沟通');

// 获取通话记录
$app->dial()->records(strtotime('-7 days'), time());
```

---

## 日程管理

```php
// 创建日程
$app->schedule()->add([
    'organizer'   => 'zhangsan',
    'start_time'  => time(),
    'end_time'    => time() + 3600,
    'attendees'   => [
        ['userid' => 'lisi'],
        ['userid' => 'wangwu'],
    ],
    'summary'     => '周会',
    'description' => '讨论下周计划',
    'location'    => '会议室A',
    'reminders'   => ['is_remind' => 1, 'remind_before_event_secs' => 3600],
]);

// 获取日程详情
$app->schedule()->get($scheduleId);

// 更新日程
$app->schedule()->update([
    'schedule_id' => $scheduleId,
    'summary'     => '更新后的标题',
    'start_time'  => time() + 7200,
]);

// 删除日程
$app->schedule()->delete($scheduleId);

// 获取日历下的日程列表
$app->schedule()->list($calId, 0, 500);
```

---

## 收集表

```php
// 创建收集表
$app->collect()->create([
    'form_title'    => '入职信息收集',
    'form_desc'     => '请填写个人信息',
    'form_question' => [
        [
            'question_id'   => 1,
            'title'         => '姓名',
            'question_type' => 'text',
        ],
        [
            'question_id'   => 2,
            'title'         => '部门',
            'question_type' => 'single_select',
            'option'        => [
                ['key' => 'tech', 'value' => '技术部'],
                ['key' => 'product', 'value' => '产品部'],
            ],
        ],
    ],
]);

// 获取收集表信息
$app->collect()->get($formid);

// 更新收集表
$app->collect()->update($formid, [
    'form_title'    => '更新后的标题',
    'form_question' => [...],
]);

// 删除收集表
$app->collect()->delete($formid);

// 获取收集表答案
$app->collect()->getAnswer($formid, 100);
```

---

## 微盘

```php
// 创建空间
$app->drive()->spaceCreate([
    'space_name' => '项目资料',
    'auth_list'  => [
        ['userid' => 'zhangsan', 'auth' => 1],  // 1=管理员
        ['userid' => 'lisi', 'auth' => 2],      // 2=编辑者
    ],
]);

// 获取空间信息
$app->drive()->spaceInfo($spaceId);

// 获取文件列表
$app->drive()->fileList($spaceId, '', 0, 100);

// 上传文件
$app->drive()->fileUpload([
    'spaceid'  => $spaceId,
    'fatherid' => $folderId,
    'file_name'=> '文档.docx',
    'file_base64_content' => base64_encode(file_get_contents('/path/to/file.docx')),
]);

// 下载文件
$app->drive()->fileDownload($spaceId, $fileId);

// 删除文件
$app->drive()->fileDelete($spaceId, $fileId);
```

---

## 上下游/互联企业

```php
// 获取应用共享信息
$app->corpGroup()->getAppShareInfo(1000002);

// UnionID 转换为外部联系人 ID
$app->corpGroup()->unionidToExternalUserid($unionid, $openid);

// 上传图片
$app->corpGroup()->uploadImage('image.jpg', file_get_contents('/path/to/image.jpg'));
```

---

## 会话内容存档

```php
// 获取开启成员列表
$app->msghub()->getPermitUserList(1);

// 获取单聊会话同意情况
$app->msghub()->getSingleAgreeStatus(['zhangsan', 'lisi']);

// 获取群聊会话同意情况
$app->msghub()->getRoomAgreeStatus(['ROOM001']);

// 获取群聊信息
$app->msghub()->getRoomInfo('ROOM001');
```

---

## 服务端消息处理

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
    return Message::toXml(Message::text('感谢关注企业号！', $payload));
});

// 处理点击菜单
$server->on('CLICK', function (array $payload, $app) {
    $key = $payload['EventKey'];
    return Message::toXml(Message::text('您点击了：' . $key, $payload));
});

// 处理进入应用
$server->on('enter_agent', function (array $payload, $app) {
    return Message::toXml(Message::text('欢迎进入应用！', $payload));
});

// 启动服务
$response = $server->serve();
$response->send();
```

---

## 支付回调通知

```php
$notify = $app->notify();

$result = $notify
    ->onPaid(function (array $payload, $app) {
        $outTradeNo = $payload['out_trade_no'];
        $totalFee   = $payload['total_fee'];

        // TODO: 更新订单状态为已支付

        return true;
    })
    ->handle();

if ($result['code'] === 'SUCCESS') {
    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
} else {
    echo '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[' . $result['message'] . ']]></return_msg></xml>';
}
```

---

## 更多参考

- [企业微信开发者中心](https://developer.work.weixin.qq.com/)
