# 钉钉使用文档

> 对应平台标识：`dingtalk`
>
> 适用场景：钉钉企业内部应用、第三方企业应用

---

## 目录

1. [配置说明](#配置说明)
2. [登录认证](#登录认证)
3. [通讯录管理](#通讯录管理)
4. [消息推送](#消息推送)
5. [审批](#审批)
6. [群机器人](#群机器人)
7. [考勤](#考勤)
8. [智能人事](#智能人事)
9. [日志管理](#日志管理)
10. [项目管理](#项目管理)
11. [智能工作流](#智能工作流)

---

## 配置说明

```php
use Kode\MiniApp\Kernel;

$kernel = new Kernel([
    'dingtalk' => [
        'app_key'    => 'dingxxxxxxxxxxxxxxxx',  // 钉钉应用 AppKey
        'app_secret' => 'your-app-secret',        // AppSecret
        'agent_id'   => '123456789',              // AgentID
    ],
]);

$app = $kernel->dingtalk()->app();
```

---

## 登录认证

### 获取 AccessToken

```php
// 获取 AccessToken（自动缓存）
$token = $app->auth()->token();
```

### 获取用户信息

```php
// 用免登码换取用户信息
$user = $app->auth()->user($code);
// 返回：['userid' => 'zhangsan', 'name' => '张三', ...]
```

---

## 通讯录管理

### 部门管理

```php
// 创建部门
$app->contact()->createDepartment([
    'name'     => '技术部',
    'parentid' => 1,
]);

// 获取部门列表
$app->contact()->getDepartmentList();

// 获取部门详情
$app->contact()->getDepartment(2);

// 更新部门
$app->contact()->updateDepartment([
    'id'   => 2,
    'name' => '研发中心',
]);

// 删除部门
$app->contact()->deleteDepartment(2);
```

### 用户管理

```php
// 创建用户
$app->contact()->createUser([
    'userid'     => 'zhangsan',
    'name'       => '张三',
    'department' => [1, 2],
    'mobile'     => '13800138000',
]);

// 获取用户详情
$app->contact()->getUser('zhangsan');

// 更新用户
$app->contact()->updateUser([
    'userid' => 'zhangsan',
    'name'   => '张三丰',
]);

// 删除用户
$app->contact()->deleteUser('zhangsan');

// 获取部门用户列表
$app->contact()->getDepartmentUsers(1);

// 获取部门用户详情列表
$app->contact()->getDepartmentUserDetails(1);
```

---

## 消息推送

### 发送工作通知

```php
// 发送文本消息
$app->message()->sendText('zhangsan', '您好！');

// 发送 Markdown
$app->message()->sendMarkdown('zhangsan', "# 标题\n\n**加粗内容**");

// 发送 OA 卡片
$app->message()->sendOA('zhangsan', [
    'message_url' => 'https://example.com',
    'head'        => ['bgcolor' => 'FFBBBBBB', 'text' => '头部标题'],
    'body'        => [
        'title' => '正文标题',
        'form'  => [
            ['key' => '姓名', 'value' => '张三'],
            ['key' => '时间', 'value' => '2024-01-01'],
        ],
    ],
]);

// 发送卡片消息
$app->message()->sendActionCard('zhangsan', [
    'title'          => '卡片标题',
    'markdown'       => '卡片内容',
    'single_title'   => '查看详情',
    'single_url'     => 'https://example.com',
]);
```

---

## 审批

### 审批实例

```php
// 创建审批实例
$app->approval()->createInstance([
    'process_code'         => 'PROC-XXX',
    'originator_user_id'   => 'zhangsan',
    'dept_id'              => 1,
    'form_component_values'=> [
        ['name' => '标题', 'value' => '请假申请'],
        ['name' => '请假类型', 'value' => '事假'],
    ],
]);

// 获取审批实例详情
$app->approval()->getInstance($processInstanceId);

// 获取审批模板列表
$app->approval()->getTemplates();
```

---

## 群机器人

### 发送消息

```php
// 发送文本
$app->robot()->sendText('webhook_url', 'Hello World');

// 发送 Markdown
$app->robot()->sendMarkdown('webhook_url', "# 标题\n\n内容");

// 发送链接
$app->robot()->sendLink('webhook_url', [
    'title'      => '链接标题',
    'text'       => '链接描述',
    'messageUrl' => 'https://example.com',
    'picUrl'     => 'https://example.com/pic.jpg',
]);

// 发送 ActionCard
$app->robot()->sendActionCard('webhook_url', [
    'title'       => '卡片标题',
    'markdown'    => '卡片内容',
    'singleTitle' => '查看详情',
    'singleURL'   => 'https://example.com',
]);

// 发送 FeedCard
$app->robot()->sendFeedCard('webhook_url', [
    'links' => [
        ['title' => '链接1', 'messageURL' => 'https://example.com/1', 'picURL' => 'https://example.com/1.jpg'],
        ['title' => '链接2', 'messageURL' => 'https://example.com/2', 'picURL' => 'https://example.com/2.jpg'],
    ],
]);
```

---

## 考勤

### 考勤组管理

```php
// 获取用户考勤组
$app->attendance()->getSimpleGroups('zhangsan');

// 获取考勤组详情
$app->attendance()->getGroup(1);
```

### 考勤记录

```php
// 获取打卡结果
$app->attendance()->getAttendanceList(
    ['zhangsan'],
    ['2024-01-01 00:00:00', '2024-01-31 23:59:59'],
    ['2024-01-01 00:00:00', '2024-01-31 23:59:59']
);

// 获取打卡详情
$app->attendance()->getAttendanceListRecord(
    ['zhangsan'],
    ['2024-01-01 00:00:00', '2024-01-31 23:59:59']
);

// 获取审批实例
$app->attendance()->getProcessInstance('INSTANCE001');
```

---

## 智能人事

```php
// 获取员工花名册字段信息
$app->hrm()->getEmpRosterDetail(['zhangsan', 'lisi']);

// 查询在职员工
$app->hrm()->queryOnJob();

// 查询待入职员工
$app->hrm()->queryPreEntry();

// 查询离职员工
$app->hrm()->queryDimission();
```

---

## 日志管理

```php
// 获取日志列表
$app->report()->list('2024-01-01 00:00:00', '2024-01-31 23:59:59');

// 获取日志详情
$app->report()->get($reportId);

// 获取日志模板列表
$app->report()->templateList();
```

---

## 项目管理

```php
// 创建项目
$app->project()->create([
    'name'        => '新项目',
    'manager_uid' => 'zhangsan',
    'description' => '项目描述',
]);

// 获取项目详情
$app->project()->get($projectId);

// 获取项目列表
$app->project()->list();

// 添加任务
$app->project()->addTask($projectId, [
    'content'      => '完成需求分析',
    'executor_uid' => 'lisi',
]);

// 获取任务列表
$app->project()->taskList($projectId);
```

---

## 智能工作流

```php
// 创建审批实例
$app->workflow()->createInstance([
    'process_code'          => 'PROC-XXX',
    'originator_user_id'    => 'zhangsan',
    'dept_id'               => 1,
    'form_component_values' => [
        ['name' => '标题', 'value' => '请假申请'],
    ],
]);

// 获取审批实例详情
$app->workflow()->getInstance($processInstanceId);

// 获取审批模板列表
$app->workflow()->templateList();

// 获取审批实例列表
$app->workflow()->instanceList([
    'process_code' => 'PROC-XXX',
    'start_time'   => strtotime('-7 days') * 1000,
]);

// 撤销审批实例
$app->workflow()->terminateInstance($processInstanceId);
```

---

## 更多参考

- [钉钉开放平台文档](https://open.dingtalk.com/)
