# 飞书使用文档

> 对应平台标识：`lark`
>
> 适用场景：飞书自建应用、第三方应用

---

## 目录

1. [配置说明](#配置说明)
2. [登录认证](#登录认证)
3. [通讯录管理](#通讯录管理)
4. [消息推送](#消息推送)
5. [审批](#审批)
6. [审批定义](#审批定义)
7. [多维表格](#多维表格)
8. [文档](#文档)
9. [日历](#日历)
10. [任务](#任务)
11. [知识库](#知识库)
12. [邮件](#邮件)

---

## 配置说明

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'lark' => [
        'app_id'     => 'cli_xxxxxxxxxxxxxxxx',  // 飞书应用 AppID
        'app_secret' => 'your-app-secret',        // AppSecret
        'encrypt_key'=> 'your-encrypt-key',        // 加密密钥（可选）
        'verification_token' => 'your-token',      // 验证 Token（可选）
    ],
]);

$app = $kernel->lark()->app();
```

---

## 登录认证

### 获取 AccessToken

```php
// 获取应用级 AccessToken（自动缓存）
$token = $app->auth()->token();
```

### 获取用户信息

```php
// 用 code 换取用户信息
$user = $app->auth()->user($code);
// 返回：['open_id' => 'ou_xxx', 'union_id' => 'on_xxx', ...]
```

---

## 通讯录管理

### 用户管理

```php
// 获取用户详情
$app->contact()->getUser('ou_xxx');

// 获取用户列表
$app->contact()->userList('0', 100);

// 批量获取用户
$app->contact()->batchGetUser(['ou_xxx', 'ou_yyy']);

// 获取部门用户列表
$app->contact()->departmentUserList('0', 100);

// 获取部门用户详情列表
$app->contact()->departmentUserDetailList('0', 100);
```

### 部门管理

```php
// 获取部门列表
$app->contact()->departmentList('0', 100);

// 获取部门详情
$app->contact()->departmentGet('0');

// 获取父部门路径
$app->contact()->departmentParent('0');
```

---

## 消息推送

### 发送消息

```php
// 发送文本消息
$app->message()->sendText('ou_xxx', 'Hello World');

// 发送富文本
$app->message()->sendPost('ou_xxx', [
    'zh_cn' => [
        'title'   => '标题',
        'content' => [
            [['tag' => 'text', 'text' => '普通文本']],
            [['tag' => 'a', 'text' => '链接', 'href' => 'https://example.com']],
        ],
    ],
]);

// 发送卡片
$app->message()->sendCard('ou_xxx', [
    'config'   => ['wide_screen_mode' => true],
    'header'   => ['title' => ['tag' => 'plain_text', 'content' => '卡片标题']],
    'elements' => [
        ['tag' => 'div', 'text' => ['tag' => 'plain_text', 'content' => '卡片内容']],
    ],
]);

// 发送图片
$app->message()->sendImage('ou_xxx', $imageKey);

// 发送文件
$app->message()->sendFile('ou_xxx', $fileKey);

// 发送语音
$app->message()->sendAudio('ou_xxx', $fileKey);

// 发送视频
$app->message()->sendMedia('ou_xxx', $fileKey);

// 发送表情包
$app->message()->sendSticker('ou_xxx', $stickerId);
```

---

## 审批

### 审批实例

```php
// 创建审批实例
$app->approval()->createInstance([
    'approval_code' => 'xxx',
    'user_id'       => 'ou_xxx',
    'form'          => [
        '控件ID' => ['value' => '值'],
    ],
]);

// 获取审批实例详情
$app->approval()->getInstance($instanceCode);

// 获取审批任务详情
$app->approval()->getTask($taskId);
```

---

## 审批定义

### 审批流程配置

```php
// 获取审批定义列表
$app->approvalDef()->list();

// 获取审批定义详情
$app->approvalDef()->get($approvalCode);

// 创建审批实例
$app->approvalDef()->createInstance([
    'approval_code' => $approvalCode,
    'user_id'       => 'ou_xxx',
    'form'          => [
        '控件ID' => ['value' => '值'],
    ],
]);

// 获取审批实例列表
$app->approvalDef()->instanceList($approvalCode);

// 审批任务同意
$app->approvalDef()->approve([
    'instance_code' => $instanceCode,
    'user_id'       => 'ou_xxx',
    'comment'       => '同意',
]);

// 审批任务拒绝
$app->approvalDef()->reject([
    'instance_code' => $instanceCode,
    'user_id'       => 'ou_xxx',
    'comment'       => '驳回',
]);

// 审批任务转交
$app->approvalDef()->transfer([
    'instance_code' => $instanceCode,
    'user_id'       => 'ou_xxx',
    'comment'       => '转交',
    'transfer_user_id' => 'ou_yyy',
]);
```

---

## 多维表格

### 表格管理

```php
// 创建多维表格
$app->bitable()->create('表格名称');

// 获取表格元数据
$app->bitable()->meta($appToken);

// 获取表格记录
$app->bitable()->records($appToken, $tableId);

// 新增记录
$app->bitable()->addRecord($appToken, $tableId, [
    'fields' => [
        '字段名' => '字段值',
    ],
]);

// 更新记录
$app->bitable()->updateRecord($appToken, $tableId, $recordId, [
    'fields' => [
        '字段名' => '新值',
    ],
]);

// 删除记录
$app->bitable()->deleteRecord($appToken, $tableId, $recordId);
```

---

## 文档

### 文档管理

```php
// 创建文档
$app->doc()->create(22, '文档标题');  // 22=文档类型

// 获取文档内容
$app->doc()->content($docToken);

// 获取文档纯文本内容
$app->doc()->rawContent($docToken);

// 获取文档元数据
$app->doc()->meta($docToken);
```

---

## 日历

### 日历管理

```php
// 创建日历
$app->calendar()->create([
    'summary'     => '日历名称',
    'description' => '日历描述',
    'permissions' => 'public',
]);

// 获取日历列表
$app->calendar()->list();

// 获取日历详情
$app->calendar()->get($calendarId);

// 删除日历
$app->calendar()->delete($calendarId);

// 订阅日历
$app->calendar()->subscribe($calendarId);

// 取消订阅
$app->calendar()->unsubscribe($calendarId);
```

### 日程管理

```php
// 创建日程
$app->calendar()->createEvent($calendarId, [
    'summary'     => '会议',
    'description' => '周会',
    'start'       => ['date_time' => '2024-01-01T10:00:00+08:00'],
    'end'         => ['date_time' => '2024-01-01T11:00:00+08:00'],
]);

// 获取日程详情
$app->calendar()->getEvent($calendarId, $eventId);

// 获取日程列表
$app->calendar()->events($calendarId, '2024-01-01T00:00:00+08:00', '2024-01-31T23:59:59+08:00');

// 删除日程
$app->calendar()->deleteEvent($calendarId, $eventId);

// 参与日程
$app->calendar()->attendEvent($calendarId, $eventId);
```

---

## 任务

### 任务管理

```php
// 创建任务
$app->task()->create([
    'summary' => '完成需求文档',
    'due'     => ['date' => '2024-01-15'],
]);

// 获取任务详情
$app->task()->get($taskGuid);

// 更新任务
$app->task()->update($taskGuid, [
    'summary' => '更新后的任务标题',
]);

// 完成任务
$app->task()->complete($taskGuid);

// 取消完成
$app->task()->uncomplete($taskGuid);

// 删除任务
$app->task()->delete($taskGuid);
```

---

## 知识库

```php
// 获取知识库列表
$app->wiki()->list();

// 获取知识库详情
$app->wiki()->get($spaceId);

// 获取知识库节点列表
$app->wiki()->nodes($spaceId);

// 创建知识库节点
$app->wiki()->createNode($spaceId, [
    'obj_type'          => 22,       // 22=文档
    'node_type'         => 'origin',
    'origin_node_token' => $docToken,
    'parent_node_token' => $parentToken,
    'title'             => '新节点',
]);
```

---

## 邮件

### 发送邮件

```php
$app->mail()->send([
    'subject' => '会议通知',
    'body'    => [
        'content'      => '明天上午10点开会',
        'content_type' => 'text/plain',
    ],
    'to'      => [
        ['mail_address' => 'user@example.com'],
    ],
]);
```

### 邮件组管理

```php
// 获取邮件组列表
$app->mail()->mailGroupList();

// 创建邮件组
$app->mail()->createMailGroup([
    'mail_group_name' => '技术部',
    'email'           => 'tech@company.com',
]);

// 获取邮件组详情
$app->mail()->getMailGroup($mailGroupId);

// 删除邮件组
$app->mail()->deleteMailGroup($mailGroupId);
```

---

## 更多参考

- [飞书开放平台文档](https://open.feishu.cn/)
