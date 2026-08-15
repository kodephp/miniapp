# Union 统一调用入口

> **设计目标**：业务侧只需要 `use Kode\MiniApp\Union\Union;` 一行代码，通过静态方法即可访问所有平台，
> 一行代码完成跨平台登录 / 支付 / 回调，跨端账号自动合并（UnionID）。

## 为什么需要 Union 统一入口

在传统 SDK 中，业务侧需要：

```php
// 原来需要 use 很多模块
use Kode\MiniApp\Providers\Wechat\Modules\Auth;
use Kode\MiniApp\Providers\Wechat\WechatProvider;
use Kode\MiniApp\Providers\Alipay\AlipayProvider;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenProvider;

// 微信小程序登录
$wechat = $kernel->wechat();
$app = $wechat->app();
$session = $app->auth()->session($code);
$openId = $session['openid'];
$unionId = $session['unionid'] ?? '';

// 微信公众号 OAuth 登录 - 又是一套不同接口
$accessToken = $app->auth()->token($code);
$userInfo = $app->user()->info($accessToken, $openId);

// 跨端账号合并 - 手动处理
// 用户先用小程序登录，又用 PC 扫码 - 需要手动查数据库合并
```

**使用 Union 后**：

```php
use Kode\MiniApp\Union\Union;

// 微信小程序登录 - 一行搞定
$user = Union::wechat()->mini($code);

// 微信公众号 - 同样一行
$user = Union::wechat()->mp($code);

// PC 扫码 - 同样一行
$user = Union::wechat()->pc($code);

// 跨端账号自动合并（同一 unionId）
$user1 = Union::wechat()->mini($code);  // unionId: u001
$user2 = Union::wechat()->pc($code);    // unionId: u001 (相同)
```

## 快速开始

```php
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Union\Union;

// 1. 初始化
$kernel = new Kernel([
    'wechat'       => [...],
    'wechat_open'  => [...],
    'alipay'       => [...],
    // ...其他平台配置
]);
$kernel->union();  // 触发 Union 初始化（之后即可用静态方法）

// 2. 业务侧只需要 use Union 类
$user = Union::wechat()->mini('JS_CODE');      // 微信小程序
$user = Union::alipay()->mini('AUTH_CODE');    // 支付宝
$order = Union::wechat()->pay()->createOrder([...]);
```

## 能力支持矩阵

> 标注「✅ 支持 / — 不适用或暂未支持」。本表为 Union 统一入口的能力覆盖总览，与各端 Provider 底层能力一致。

| 平台 | 登录 | 用户资料 | 客户端解密(encryptedData) | 手机号(code 换) | 手机号(encryptedData) | 支付 | 回调通知 |
|------|------|----------|---------------------------|-----------------|----------------------|------|----------|
| 微信（小程序 / 公众号 / H5 / PC / App） | ✅ | ✅ | ✅ | ✅ 小程序 | ✅ | ✅ 小程序/公众号/App | ✅ 全场景 |
| 微信开放平台 | ✅ | ✅ | — | — | — | — | — |
| 支付宝（小程序 / 生活号 / App） | ✅ | ✅ | ✅ response+sign | — | — | ✅ mini/mp/app | ✅ |
| 抖音（小程序） | ✅ | ✅ | ✅ | ✅ RSA 密文 | ✅ | ✅ 小程序 | ✅ 小程序 |
| 百度（小程序） | ✅ | ✅ | ✅ | — | ✅ | ✅ 小程序 | ✅ 小程序 |
| QQ（小程序） | ✅ | ✅ | ✅ | — | ✅ | ✅ 小程序 | ✅ 小程序 |
| 企业微信 | ✅ | ✅ | ✅ | — | ✅ | —（经 kode/pays） | ✅ |
| 钉钉 | ✅ | ✅ | — | — | — | — | — |
| 飞书 | ✅ | ✅ | ✅ hex 变体 | — | ✅ | — | — |

说明：

- **客户端解密(encryptedData)**：微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 走统一 `Union::decrypt()` / `decryptByUser()`（AES-128-CBC + watermark，飞书为 hex 变体）；支付宝走 `Union::alipay()->decrypt()`（response+sign / RSA2 验签），不并入统一入口。
- **手机号(code 换)**：微信小程序 `Union::phoneByCode()`（明文 `phone_info`）；抖音 `Union::phoneByCode()`（RSA 密文，需 `app_private_key`）。
- **手机号(encryptedData)**：微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 走 `Union::phoneByDecrypt()` / `phoneByUser()`；支付宝走 `Union::phoneByResponse()`。
- **支付 / 回调**：B 端平台（钉钉 / 飞书）及微信开放平台（第三方平台）无消费者支付场景，标记为「—」属设计预期。QQ 回调为 XML + MD5（`api_key`）验签，由 `Union::qq()->notify()` 提供。

## Union 静态门面

通过 `__callStatic` 魔术方法，Union 类把平台方法统一为：

| 静态方法 | 返回 | 说明 |
|----------|------|------|
| `Union::wechat()` | `WechatUnion` | 微信生态聚合 |
| `Union::wechatOpen()` / `Union::openPlatform()` | `WechatOpenPlatformUnion` | 微信开放平台 |
| `Union::alipay()` | `AlipayUnion` | 支付宝聚合 |
| `Union::douyin()` | `DouyinUnion` | 抖音聚合 |
| `Union::baidu()` | `BaiduUnion` | 百度智能小程序 |
| `Union::qq()` | `QqUnion` | QQ 聚合 |
| `Union::wechatWork()` / `Union::work()` | `WechatWorkUnion` | 企业微信聚合 |
| `Union::dingtalk()` | `DingtalkUnion` | 钉钉聚合 |
| `Union::lark()` | `LarkUnion` | 飞书聚合 |

## 平台聚合类（Platform Union）

每个平台聚合类（`WechatUnion`、`AlipayUnion` 等）提供以下统一方法：

### 场景登录

| 方法 | 适用场景 |
|------|----------|
| `->mini($code)` | 小程序 / 默认登录 |
| `->mp($code)` | 公众号 |
| `->h5($code)` | H5 |
| `->pc($code)` | PC 网站应用 |
| `->app($code)` | 移动 App |
| `->open($payload)` | 开放平台 |
| `->suite($code)` | 企业微信套件 |
| `->login($payload, $scene)` | 通用登录（自定义 payload + 场景） |

### 四大能力

```php
// 1. 登录 - 统一返回 UnionUser
$user = Union::wechat()->mini($code);

// 2. 用户资料 - 通过 openId 拉取
//    公众号 / H5：自动解析 mp access_token，无需手动传入
$user = Union::wechat()->user($openId, [], 'mp');
//    小程序：服务端无法拉取，使用客户端上报（已解密）的资料
$user = Union::wechat()->user($openId, ['raw' => $clientUserInfo], 'mini');
//    开放平台移动 / 网站应用：传入登录时获取的 OAuth access_token
$user = Union::wechat()->user($openId, ['access_token' => $token], 'app');

// 3. 支付 - 统一下单（本包登录拿身份 → kode/pays 收钱）
//    需先 composer require kode/pays（2.0 起支付完全由 kode/pays 承载，二者为一对一契约）
$user  = Union::wechat()->mini($code);   // 身份：本包登录
$order = Union::wechat()->pay()->createOrder([
    'out_trade_no' => 'O001',
    'description'  => '商品',
    'amount'       => ['total' => 100],  // 分（V3 结构）
], $user);                               // openid 由 PaysBridge 自动注入

// 4. 回调 - 验证 + 解析（委托 kode/pays 验签 + 解密，返回可信业务数组）
$data = Union::wechat()->notify()->decode($payload, $headers);
```

### 高级支付能力（分账 / 转账 / 对账）

核心下单 / 退款 / 关单 / 验签之外，kode/pays 网关还提供「特色方法」（分账、转账、对账等）。
本包通过 {@see \Kode\MiniApp\Union\Contracts\AdvancedPayAdapter} 暴露这些能力，由
{@see \Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter} 以 `method_exists` 守卫委托真实网关，
**方法名与 kode/pays 网关契约完全一致**，无额外封装、无参数变换：

```php
// 取得高级支付能力适配器（分账 / 转账 / 对账）
$adv = Union::wechat()->advancedPay();   // 等价于 $union->advancedPay(Channel::WechatMini)

// 分账（ProfitSharingCapableInterface）
$adv->profitSharingCreate([
    'transaction_id' => '微信订单号',
    'out_order_no'   => '商户分账单号',
    'receivers'      => [['type' => 'MERCHANT_ID', 'account' => 'mch_2', 'amount' => 100, 'description' => '分账']],
]);
$adv->profitSharingQuery('商户分账单号', '微信订单号');     // 查询分账结果（微信需传 transaction_id）
$adv->profitSharingReturn(['out_order_no' => 'X', 'out_return_no' => 'R', 'return_amount' => 100]); // 分账回退
$adv->profitSharingQueryReturn('商户回退单号');             // 查询分账回退结果
$adv->profitSharingUnfreeze('微信订单号');                  // 解冻未分账的剩余资金

// 转账 / 企业付款（TransferCapableInterface）
$adv->transferSingle([
    'out_biz_no' => '商户转账单号',
    'amount'     => 100,
    'recipient'  => ['type' => 'openid', 'account' => $openId, 'name' => '张三'],
]);
$adv->transferBatch([/* out_biz_no + transfer_detail_list */]); // 批量转账
$adv->transferQuery('商户转账单号');                        // 查询转账结果
$adv->transferReceipt('商户转账单号');                      // 查询转账电子回单

// 对账（ReconciliationCapableInterface）
$bill = $adv->reconciliationDownloadBill(['bill_date' => '20260814']); // 下载交易对账单
$flow = $adv->reconciliationDownloadFundFlow(['bill_date' => '20260814']); // 下载资金账单
$records = $adv->reconciliationParseBill($bill['raw_data']);     // 解析对账单原始数据（CSV / JSON）
```

> **能力可用性**：分账 / 转账 / 对账并非所有平台 / 网关都具备（如百度、企业微信网关未实现；
> 微信分账需先在商户平台开通）。当某渠道的网关未实现对应特色方法时，`advancedPay()` 返回的适配器
> 会抛清晰异常（含「分账 / 转账 / 对账」字样），不会触发难以定位的「Call to undefined method」。
> 是否需要该能力由调用方按业务自行判断，本包不替业务做能力裁剪。

### 透传底层 Provider / App

如需细粒度控制（如素材管理、菜单管理、JS-SDK 等）：

```php
$wechat = Union::wechat();

// 获取平台 App 实例（含 30+ 能力模块）
$app = $wechat->appInstance();
$app->message()->send($openId, 'text', 'Hello');
$app->media()->upload('image', '/path/to/image.jpg');
$app->menu()->create([...]);
$app->jssdk()->config($url, ['chooseImage']);
$app->subscribeMessage()->send($openId, $templateId, $data);

// 获取 Provider
$provider = $wechat->provider();
```

## 跨端账号合并（UnionID）

配置好微信开放平台（同一开放平台下绑定所有应用）后，跨端账号自动合并：

```php
// 用户先用小程序登录
$user1 = Union::wechat()->mini('JS_CODE');        // unionId: u001

// 再用 PC 扫码
$user2 = Union::wechat()->pc('PC_CODE');          // unionId: u001 (相同)
$user3 = Union::wechat()->app('APP_CODE');        // unionId: u001 (相同)
$user4 = Union::wechat()->h5('H5_CODE');          // unionId: u001 (相同)
$user5 = Union::wechat()->mp('OAUTH_CODE');       // unionId: u001 (相同)

// 业务侧只需用 unionId 关联业务账号
$businessUser = User::where('union_id', $user1->unionId)->first();
```

## 微信开放平台绑定：公众号 / 小程序一键登录 + 用户信息

将公众号、小程序、移动 App、网站应用绑定到**同一个微信开放平台**后，同一用户在各端登录都会得到相同的 `unionId`，业务侧据此关联同一用户（即"一键登录、多端通用"）。

```php
use Kode\MiniApp\Union\Union;

// 1) 一键登录：各端一行代码，自动返回相同的 unionId
$mini = Union::wechat()->mini('JS_CODE');   // 小程序
$mp   = Union::wechat()->mp('OAUTH_CODE');  // 公众号 OAuth
$pc   = Union::wechat()->pc('PC_CODE');     // 网站应用扫码
$app  = Union::wechat()->app('APP_CODE');   // 移动 App

// 2) 获取用户信息：登录后通过 openId 拉取
//    公众号 / H5：无需手动传 token，适配器自动解析 mp access_token
$profile = Union::wechat()->user($mp->openId, [], 'mp');
//    小程序：服务端无法拉取，使用客户端上报（已解密）的资料
$profile = Union::wechat()->user($mini->openId, ['raw' => $clientUserInfo], 'mini');
//    开放平台 App / PC：传入登录时获取的 OAuth access_token
$profile = Union::wechat()->user($app->openId, ['access_token' => $oauthToken], 'app');

// 3) 关联业务账号：unionId 在所有绑定应用中一致
$bizUser = User::where('union_id', $mini->unionId)->first();
```

> 注意：小程序没有服务端用户资料接口，`nickname` / `avatar` 需由客户端通过 `wx.getUserProfile` 取得后随登录一并上报（经 `raw` 传入）。

### 错误处理（真实对接）

所有平台登录均按各开放平台真实接口契约校验错误，避免「无效 code / 过期 token」被静默当成成功：

- **微信系**：小程序 `jscode2session`、公众号 `sns/oauth2/access_token`、开放平台 App/PC `sns/oauth2/access_token` 在微信返回 `errcode`（如 `40029 invalid code`、`40013 invalid appid`）时抛出 `Kode\MiniApp\Exceptions\ApiException`。
- **支付宝**：网关错误响应挂在独立的 `error_response` 节点（不以 `alipay_` 开头），SDK 已专门识别并在 `code != 10000` 时抛出 `ApiException`（此前该节点未被识别，错误会被静默吞掉）。支付宝无 unionid 概念，`unionId` 恒为空。
- **抖音 / QQ / 百度 / 企业微信 / 钉钉 / 飞书**：分别按 `err_no` / `errcode` / `error`(OAuth) / `errcode` / `errcode` / `code` 字段校验，失败时同样抛出 `ApiException`。
- **用户资料拉取（profile）同样校验错误**：抖音 `apps/v2/user/get_profile`（`err_no`）、QQ `graph.qq.com/user/get_user_info`（错误字段为 `ret`，与登录接口的 `errcode` 不同）、百度 `smartapp/getuserinfo`（错误字段为 `errno`，与授权接口的 `error` 不同）、企业微信 `cgi-bin/user/get`（`errcode`）、**微信 `cgi-bin/user/info` 与开放平台 `sns/userinfo`（`errcode`，`48001` 为预期内空资料不抛错，其余如 `40001` 令牌失效统一抛 `ApiException`）**、支付宝 `alipay.user.info.share`（错误节点 `error_response`）、钉钉 `topapi/v2/user/get`（`errcode`）、飞书 `contact/v3/users`（`code`），均在校验失败后抛出 `ApiException`，彻底杜绝资料接口的静默失败。QQ / 百度需调用方传入用户 `access_token`（payload `access_token`）；抖音、微信、企业微信、支付宝、钉钉、飞书未传时自动回退到各自服务端 token。
- **飞书嵌套字段归一化**：飞书 `contact/v3/users` 返回的 `name`（对象：`zh_cn`/`en_us`）与 `avatar`（对象：`avatar_origin`/`avatar_240`/`avatar_72`）为嵌套结构，适配器已将其归一化为 `nick_name` / `avatar_url`，避免昵称与头像被静默丢失（该 bug 在 v1.20.0 修复）。
- **企业微信资料修复**：此前 `Channel::WechatWork` 的 profile 被错误路由到微信（Wechat）适配器，根本取不到企业微信成员资料（要么调用错误 Provider、要么静默返回空）。现已新增独立的 `WeWorkUserAdapter`，以 userid 为键调 `/user/get` 拉取姓名 / 头像 / 部门 / 职位等真实资料。
- 开放平台「第三方平台代公众号/小程序」授权（`authorization_code` 换 `authorizer_access_token`）在微信返回 `errcode` 时抛出 `RuntimeException`，错误信息含 `errmsg`。
- 拉取用户资料（`sns/userinfo` / `cgi-bin/user/info`）若授权范围不足（如 `48001` 用户未关注 / 未授权 userinfo）不会抛错，仅返回空资料，由业务侧决定是否补充授权；但 `40001`（令牌失效）、`40003`（openid 非法）、`50001`（未授权）等真实错误现已统一抛出 `ApiException`（此前被静默吞进占位 `UnionUser`，业务侧无法感知）。该「预期内错误 vs 真实错误」的判定收敛在 `WechatProfileError` 一处，微信系所有资料拉取路径复用。

> 端到端测试覆盖：`tests/Union/WechatLoginTest.php`、`tests/Union/OpenPlatformLoginTest.php`（微信系）、`tests/Union/PlatformLoginTest.php`（支付宝 / 抖音 / QQ / 百度 / 企业微信 / 钉钉 / 飞书 登录）、`tests/Union/UserProfileTest.php`（微信 mp / 微信开放平台 App + 抖音 / QQ / 百度 / 企业微信 / 支付宝 / 钉钉 / 飞书 用户资料拉取），每个渠道均验证「成功提取 openid/unionid/昵称/头像」与「无效 code / token / userid / openid 真实抛错」。

```php
use Kode\MiniApp\Exceptions\ApiException;

try {
    $user = Union::wechat()->app('APP_CODE');
} catch (ApiException $e) {
    // $e->errorCode() 为微信 errcode，$e->message() 为 errmsg
    echo $e->errorCode() . ': ' . $e->message();
}
```

## 客户端敏感数据解密（encryptedData）

小程序端 `getUserProfile` / `getPhoneNumber` 等接口回传的 `encryptedData` 是**AES 加密的 JSON**，必须由服务端解密。微信、抖音、QQ 三端采用**同一套对称算法**（`AES-128-CBC` + `PKCS#7` + `watermark`），SDK 统一封装为共享工具 `Core\Crypto\Aes128CbcPkcs7`，三端 `Decrypt` 模块复用，杜绝重复实现。

**支付宝算法不同**：`my.getPhoneNumber` 回传 `response`（密文）+ `sign`（RSA2 签名），无 `session_key` / `iv`，需用开放平台配置的 **AES 密钥**（config `aes_key`，base64 编码 16 字节）以 `AES-128-CBC` + **全零 IV** 解密，解密后 JSON 含 `mobile` 字段。因此支付宝`Decrypt` 为独立实现（不复用上述共享工具）。

- **通用算法（微信 / 抖音 / QQ）**：`AES-128-CBC`，`key = base64_decode(session_key)`、`iv = base64_decode(iv)`，密文 `base64_decode(encryptedData)`，PKCS#7 由 openssl 自动去填充。
- **支付宝算法**：`AES-128-CBC`，`key = base64_decode(aes_key)`、`iv = 16 字节全零`，密文 `base64_decode(response)`；`sign` 以 config `public_key` 做 RSA2 验签防篡改。
- **安全约束（通用）**：解密结果含 `watermark.appid`，必须与当前小程序 `appId` 一致，否则视为伪造数据并抛 `ApiException`。生产环境务必保持默认开启（`verifyAppId = true`）。
- **敏感凭证**：`session_key` / `aes_key` 属密钥级敏感信息，**切勿下发到前端、切勿写入日志**（`LogSanitizer` 已对二者脱敏）。

### 支持的渠道

| 渠道 | Union 入口 | 直接模块 |
| --- | --- | --- |
| 微信小程序 / 公众号 / 开放平台 | `Union::decrypt(Channel::WechatMini/Mp/App, $encryptedData, $sessionKey, $iv)` | `WechatApp::decrypt()` |
| 抖音小程序 / 抖音 PC | `Union::decrypt(Channel::DouyinMini/Mp, $encryptedData, $sessionKey, $iv)` | `DouyinApp::decrypt()` |
| QQ 小程序 | `Union::decrypt(Channel::Qq, $encryptedData, $sessionKey, $iv)` | `QqApp::decrypt()` |
| 百度小程序 | `Union::decrypt(Channel::BaiduMini, $encryptedData, $sessionKey, $iv)` | `BaiduApp::decrypt()` |
| 飞书小程序 | `Union::decrypt(Channel::Lark, $encryptedData, $sessionKey, $iv)` | `LarkApp::decrypt()` |
| 企业微信小程序 | `Union::decrypt(Channel::WechatWork, $encryptedData, $sessionKey, $iv)` | `WechatWorkApp::decrypt()` |
| 支付宝小程序 / 生活号 / App | `Union::alipay()->decrypt()->phone($response, $sign)` | `AlipayApp::decrypt()` |

> 支付宝解密算法无 `session_key` / `iv`，与通用 4 参 `Union::decrypt()` 签名不兼容，故**不并入** `Union::decrypt()`（对其调用 `Channel::AlipayMini` 仍抛 `InvalidArgumentException`），改由 `Union::alipay()->decrypt()` 访问，与其他平台一致的 `decrypt()` 访问器。

> 飞书底层同为 AES-128-CBC，但其 `session_key` / `iv` 采用 **hex 编码**（微信系为 base64），明文**不含 watermark**。统一解密工具 `Core\Crypto\Aes128CbcPkcs7` 通过 `$encoding = 'hex'` 参数兼容该变体，`LarkApp::decrypt()` 默认 `verifyAppId = false`（跳过 watermark 校验）。钉钉登录走 `user/getuserinfo` by code，无小程序 `session_key` 托管与客户端解密场景，故**不在**统一解密覆盖范围内。

> 企业微信小程序与微信同属 AES-128-CBC + PKCS#7。企业微信官方明确：解密后明文 `watermark.appid` 为**小程序 appId**，**并非**企业 corpid，故 `WechatWorkApp::decrypt()` 以 `config->appId()`（配置键 `app_id`）校验 watermark；未配置 `app_id` 时解密抛清晰错误提示。其 `Auth::session($code)` 调 `miniprogram/jscode2session`（需先取 `access_token`），返回 `session_key` / `openid` / `userid` 并自动托管 session_key；注意这与「企业内部应用」的 `Auth::user($code)`（code→userid）是两套独立流程，互不干扰。

### 用法一：通过 Union 统一入口

```php
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;

// 微信 / 抖音 / QQ：前端回传 encryptedData、iv、登录阶段缓存的 session_key
$data = Union::wechat()->decrypt(Channel::WechatMini, $encryptedData, $sessionKey, $iv);
$data = Union::douyin()->decrypt(Channel::DouyinMini, $encryptedData, $sessionKey, $iv);
$data = Union::qq()->decrypt(Channel::Qq, $encryptedData, $sessionKey, $iv);
// $data['nickName'] / $data['avatarUrl'] / $data['watermark'] ...

// 支付宝：前端回传 response（密文）+ sign（RSA2 签名），无 session_key / iv
$phone = Union::alipay()->decrypt()->phone($response, $sign);
// $phone['mobile'] / $phone['countryCode'] ...（另含归一化的 phoneNumber / purePhoneNumber / countryCode）
```

### 用法二：直接走 App 模块

```php
use Kode\MiniApp\Kernel;

// 微信
$app = (new Kernel(['wechat' => ['app_id' => 'wx...', 'app_secret' => '...']]))->wechat()->app();
$raw  = $app->decrypt()->data($encryptedData, $sessionKey, $iv);   // 原始数组（含 watermark 校验）
$user = $app->decrypt()->userInfo($encryptedData, $sessionKey, $iv); // 同 data()，语义化别名
$phone = $app->decrypt()->phone($encryptedData, $sessionKey, $iv);  // 手机号，缺字段抛 ApiException

// 支付宝：需配置 aes_key（16 字节 base64）+ public_key（验签）
$alipay = (new Kernel(['alipay' => [
    'app_id'     => 'alipay...',
    'aes_key'    => base64_encode('16字节密钥'),
    'public_key' => '支付宝公钥（用于验签）',
]]))->alipay()->app();
$phone = $alipay->decrypt()->phone($response, $sign); // sign 可空，传则先 RSA2 验签
```

抖音 / QQ 的 `Decrypt` 模块提供完全一致的 `data()` / `userInfo()` / `phone()` 三方法；支付宝 `Decrypt` 提供 `data($response)` / `phone($response, ?$sign)` / `verifySign($response, $sign)`。

### session_key 托管 + 一站式解密（登录即缓存）

微信 / 抖音 / QQ 解密必须持有 `session_key`，而它只在登录（`code2session`）时一次性返回。手动在登录与解密之间传递 `session_key` 既繁琐又易出错。SDK 提供 `Core\SessionKeyManager` 自动托管：

- **登录即缓存**：`Auth::session()` 登录成功后，自动把 `session_key` 按 `openid` 存入 PSR-16 缓存（与 `access_token` 共用同一套缓存配置），无需任何额外代码。
- **一站式解密**：解密时不再需要传 `session_key`，只需传 `openid`，SDK 自动取回该用户托管的密钥。

```php
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;

// 1) 登录：session_key 已在底层自动托管（按 openid 缓存）
$user  = Union::wechat()->mini($code);
// $user->openId 即缓存键

// 2) 解密：只传 encryptedData + iv + openId，session_key 自动取用
$phone = Union::decryptByUser(Channel::WechatMini, $encryptedData, $iv, $user->openId);
// 等价地，直接走 App 模块：
$phone = (new Kernel([...])->wechat()->app())
    ->decrypt()->phoneByUser($encryptedData, $iv, $user->openId);

// 抖音 / QQ 完全一致：
Union::decryptByUser(Channel::DouyinMini, $encryptedData, $iv, $openId);
Union::decryptByUser(Channel::Qq, $encryptedData, $iv, $openId);
```

各端 `Decrypt` 配套提供 `dataByUser()` / `phoneByUser()` / `userInfoByUser()`（签名：`($encryptedData, $iv, $openId)`）；`Union` 提供 `decryptByUser(Channel, $encryptedData, $iv, $openId)`。若对应 `openid` 未托管 `session_key`，则抛 `ApiException`（提示先登录或手动托管）。

**缓存配置**（写在平台配置数组中，与 `access_token` 配置约定一致）：

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `cache` | `Cache::getInstance()` | PSR-16 实例（生产建议 Redis / Memcached，保证多 worker 共享） |
| `session_key_cache` | `true` | 设为 `false` 关闭托管（调试 / 不想落缓存时） |
| `session_key_ttl` | `null` | 缓存秒数；`null` 表示不过期，重新登录会覆盖旧值 |

手动托管 / 取回 / 清除：

```php
use Kode\MiniApp\Core\SessionKeyManager;

$manager = SessionKeyManager::for($app->config());
$manager->store($openId, $sessionKey);   // 手动托管
$sk      = $manager->get($openId);        // 取回（未托管 / 过期返回 null）
$manager->forget($openId);                // 用户注销 / session 失效时清除
```

> 注意：支付宝解密走 `aes_key` + `sign`，无 `session_key`，因此**不参与**此托管机制（仍用 `Union::alipay()->decrypt()`）。

### 失败语义（统一抛 ApiException）

| 场景 | 行为 |
| --- | --- |
| `watermark.appid` 与当前 `appId` 不一致（微信 / 抖音 / QQ） | 抛 `ApiException`（伪造数据） |
| `session_key` / `iv` / `encryptedData` base64 非法 | 抛 `ApiException` |
| 密钥或向量长度非 16 字节 | 抛 `ApiException` |
| `session_key` 错误导致解密出乱码（非 JSON） | 抛 `ApiException` |
| 手机号密文缺少 `phoneNumber` 等字段 | 抛 `ApiException` |
| 需临时关闭 appId 校验（verifyAppId=false） | 返回原始数组，不校验（仅特殊场景） |
| 支付宝 `aes_key` 配置非法（非 16 字节 base64） | 抛 `ApiException` |
| 支付宝传入 `sign` 但 RSA2 验签不通过 | 抛 `ApiException` |
| 支付宝解密结果缺少 `mobile` 字段 | 抛 `ApiException` |
| 支付宝 `verifySign` 但 `public_key` 未配置 | 抛 `ApiException` |

> 端到端测试：`tests/Providers/{Wechat,Douyin,Qq,Baidu}/DecryptTest.php`（各端真实 AES round-trip、手机号、watermark 校验失败、错误密钥、非法 base64 / 长度，以及 `dataByUser/phoneByUser` 一站式解密 + 未托管抛错）、`tests/Providers/Lark/DecryptTest.php`（飞书 hex 变体：`session_key`/`iv` 为 hex 编码、密文 base64、明文无 watermark，覆盖手机号 / 用户信息 / 错误密钥 / 非法 hex / 长度非法 / `ByUser` 一站式）、`tests/Providers/WechatWork/DecryptTest.php`（企业微信：watermark.appid 以小程序 appId 校验（corpid 不再被接受）、缺 app_id 配置抛错、手机号 / 资料 / 错误密钥 / 非法 base64 / 长度非法 / `ByUser` 一站式 + 未托管抛错）、`tests/Providers/{Wechat,Baidu}/AuthSessionKeyStoreTest.php`（登录自动托管 session_key）、`tests/Providers/Lark/AuthSessionKeyStoreTest.php`（飞书登录 `app_access_token` + `tokenLoginValidate` 自动托管 session_key）、`tests/Providers/WechatWork/AuthSessionKeyStoreTest.php`（企业微信 `gettoken` + `jscode2session` 自动托管 session_key、缺失不写入）、`tests/Core/SessionKeyManagerTest.php`（store/get/forget/TTL/关闭托管/配置解析）、`tests/Providers/Alipay/DecryptTest.php`（真实 AES-128-CBC + 全零 IV round-trip、缺 mobile、`aes_key` 非法、RSA2 验签成功 / 失败、公钥缺失）、`tests/Union/DecryptTest.php`（微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 / 支付宝分派成功 + `decryptByUser` 一站式 + 不支持渠道抛错）。加密向量均采用与官方完全一致的算法生成，即是对「真实对接」的端到端验证。

## 手机号快速验证（新版 code 换手机号）

微信自基础库 **2.21.2** 起对获取手机号做了安全升级：`<button open-type="getPhoneNumber">` 回调不再返回 `encryptedData` + `iv`，而是返回**动态令牌 `code`**，由服务端消费 code 直接换取手机号，**不再需要 `wx.login`，也不依赖 `session_key`**。

与上一节的 `Union::decrypt()`（旧版 encryptedData 解密）互为两条并行路径，旧方式仍可用，官方建议改用新方式。

| 渠道 | Union 入口 | 直接模块 |
| --- | --- | --- |
| 微信小程序 | `Union::phoneByCode(Channel::WechatMini, $code)` | `WechatApp::phone()` |
| 抖音小程序 | `Union::phoneByCode(Channel::DouyinMini, $code)` | `DouyinApp::phone()` |

两端返回的数组字段结构一致（`phoneNumber` / `purePhoneNumber` / `countryCode` / `watermark`），差异（微信返回明文、抖音返回 RSA 密文）由 SDK 内部消化，业务侧无需感知。

> **输出归一化**：各端字段命名并不完全一致（支付宝解密结果为 `mobile` 而非 `phoneNumber`）。为此 SDK 提供 `Core\PhoneNormalizer`：
> - `Union::phoneByCode()` / `Union::phoneByDecrypt()` / `Union::phoneByUser()` 返回已通过归一化兜底为 `phoneNumber` / `purePhoneNumber` / `countryCode`（原字段全部保留）；
> - 支付宝 `Union::phoneByResponse()`（统一入口，推荐）在保留 `mobile` 的同时，追加上述三元组，与其余端一致；底层等价写法是 `Union::alipay()->decrypt()->phone()`；
> - 对任意原始数组，可主动调用静态方法 `Union::normalizePhone($raw)` 得到统一结构（`phoneNumber` / `purePhoneNumber` / `countryCode`），缺失字段以空字符串填充，绝不抛异常。

```php
use Kode\MiniApp\Union\Channel;

// 前端：e.detail.code 回传服务端
$info = $kernel->union()->phoneByCode(Channel::WechatMini, $code);

$info['phoneNumber'];      // +8613800138000（带区号）
$info['purePhoneNumber'];  // 13800138000（不带区号）
$info['countryCode'];      // 86
$info['watermark'];        // ['timestamp' => ..., 'appid' => ...]
```

也可直接使用模块访问器，并提供两个便捷方法：

```php
$phone = $kernel->wechat()->app()->phone();

$phone->byCode($code);                  // 完整 phone_info 数组
$phone->byCode($code, $openId);         // 可选透传 openid（官方选填参数）
$phone->numberByCode($code);            // '+8613800138000'
$phone->pureNumberByCode($code);        // '13800138000'
```

底层调用 `POST https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=...`，`access_token` 由 `Auth::token()` 自动获取并复用缓存。

### 抖音小程序（RSA 密文变体）

抖音自基础库 **3.51.0** 起提供同类能力，但返回的是**用应用公钥 RSA 加密的密文**而非明文，需由 SDK 用应用私钥解密。使用前必须完成两步配置：

1. 在「抖音开放平台 - 控制台 - 开发 - 开发配置 - 应用公钥」录入自行生成的 RSA **公钥**；
2. 把对应的**私钥**写入 SDK 配置项 `app_private_key`。

```php
$kernel = new Kernel([
    'douyin' => [
        'app_id'          => 'tt...',
        'app_secret'      => '...',
        // 应用私钥：支持完整 PEM / 纯 Base64 / PKCS#1 / PKCS#8 四种写法
        'app_private_key' => file_get_contents('/path/to/douyin_app_private_key.pem'),
    ],
]);

$info = $kernel->union()->phoneByCode(Channel::DouyinMini, $code);

// 或直接使用模块访问器
$phone = $kernel->douyin()->app()->phone();
$phone->byCode($code);            // 解密后的完整数组
$phone->numberByCode($code);      // '+8613800138000'
$phone->pureNumberByCode($code);  // '13800138000'
```

与微信的三点差异：

| 维度 | 微信 | 抖音 |
| --- | --- | --- |
| 凭证 | 小程序 `access_token` | 开放平台 `client_token`（`open.douyin.com/oauth/client_token/`，与 `access_token` 是两套独立凭证，SDK 自动获取并缓存） |
| 返回 | 明文 `phone_info` | base64(RSA-PKCS#1v15 密文)，SDK 用 `app_private_key` 自动解密 |
| `openid` 参数 | 支持选填 | 接口不接受，传入会被忽略 |

解密由共享工具 `Core\Crypto\RsaPkcs1` 完成（支持超长明文分段解密），与对称族的 `Core\Crypto\Aes128CbcPkcs7` 并列。解密后同样会校验 `watermark.appid` 与当前小程序一致，防止跨应用重放。

> 平台更新应用公钥后会**立即**改用新公钥加密，须同步更新 `app_private_key`，否则解密失败。应用私钥属最高级敏感凭证，严禁写入日志或下发客户端。
>
> 手动管理 client_token：`$phone->clientToken()` / `refreshClientToken()` / `forgetClientToken()`。

**约束与注意事项**

- 每个 `code` **仅可消费一次**；微信有效期 **5 分钟**，抖音过期报 `28005187`。
- 该 code 与登录 code（`wx.login` / `tt.login`）**作用不同、不可混用**（微信混用报 `40029`）。
- 微信侧该能力仅对**非个人主体且已认证**的小程序开放，且**按次计费**（政府 / 非营利组织等主体可免费）。
- 请求 appid 与获取 code 的小程序 appid 不匹配，微信报 `40013`；抖音则在 watermark 校验环节被 SDK 拦截。

**覆盖范围说明**

| 平台 | 是否支持 code 换手机号 | 说明 |
| --- | --- | --- |
| 微信小程序 | ✅ 已支持 | `wxa/business/getuserphonenumber`，返回明文 `phone_info` |
| 抖音小程序 | ✅ 已支持 | `api/apps/v1/get_phonenumber_info/`，返回 RSA 密文，SDK 用 `app_private_key` 自动解密 |
| 百度 / QQ 小程序 | ❌ 无此范式 | 仍只提供 `encryptedData` + `session_key` 解密，见上一节 |
| 支付宝 | ✅ 已支持（独立范式） | 走 `response` + `sign` / RSA2，经 `Union::phoneByResponse()`，见下方独立小节 |

对不支持的渠道调用 `Union::phoneByCode()` 会抛出 `InvalidArgumentException`。

### 统一加密手机号入口（encryptedData，旧版路径）

若前端回传的是 `encryptedData` + `iv`（旧版 `<button open-type="getPhoneNumber">` 回调，或飞书等以加密数据返回手机号的端），可用与 `phoneByCode()` 对称的统一入口：

| 渠道 | Union 入口 | 说明 |
| --- | --- | --- |
| 微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 | `Union::phoneByDecrypt($channel, $encryptedData, $sessionKey, $iv)` | 显式传入 `session_key` 解密 |
| 同上 | `Union::phoneByUser($channel, $encryptedData, $iv, $openId)` | 自动取用登录阶段托管的 `session_key`，无需手动传入 |

两方法返回结构均经 `Core\PhoneNormalizer` 归一化为 `phoneNumber` / `purePhoneNumber` / `countryCode`（原字段全部保留）。支付宝不在覆盖范围内（其手机号走 `response` + `sign`，无 `encryptedData` / `session_key`），调用会抛 `InvalidArgumentException`。

```php
// 显式 session_key
$info = $kernel->union()->phoneByDecrypt(
    Channel::WechatMini, $encryptedData, $sessionKey, $iv
);
$info['phoneNumber'];     // 13800138000

// 缓存 session_key（登录时已 Union::wechat()->mini($code) 托管）
$info = $kernel->union()->phoneByUser(
    Channel::WechatMini, $encryptedData, $iv, $openId
);
```

> 飞书小程序的手机号即走此路径：`tt.getPhoneNumber` 返回 `encryptedData` + `iv`（hex 编码的 `session_key`），SDK 内部自动兼容，无需也不支持 code 换手机号。

### 统一支付宝手机号入口（response + sign）

支付宝小程序 `my.getPhoneNumber` 前端回传的是加密 `response`（AES-128-CBC，全零 IV，`key = base64_decode(aes_key)`）与 RSA2 `sign`，**既无 code 也无 encryptedData / session_key**，输入形态与微信 / 抖音（code）、QQ / 百度 / 飞书 / 企业微信（encryptedData）都不同。v1.33.0 起将其也纳入 `Union` 的统一手机号家族（打破原设计 fence）：

| 渠道 | Union 入口 | 说明 |
| --- | --- | --- |
| 支付宝小程序 / 生活号 / APP | `Union::phoneByResponse($channel, $response, $sign)` | `$sign` 可选，传入则先做 RSA2 验签 |

```php
$info = $kernel->union()->phoneByResponse(
    Channel::AlipayMini, $response, $sign
);
$info['mobile'];          // 13800138000
$info['phoneNumber'];     // 13800138000（已归一化）
$info['countryCode'];     // 86
```

- 传入 `$sign` 时先用 `config.public_key` 做 RSA2 验签，验签失败直接抛 `ApiException`（防中间人篡改，生产环境强烈建议传）；
- 不传 `$sign` 则跳过验签，仅完成解密（失去篡改防护，不推荐）；
- 非支付宝渠道调用抛 `InvalidArgumentException`。
- 返回结构经归一化，含 `mobile` / `countryCode` 及统一三元组 `phoneNumber` / `purePhoneNumber` / `countryCode`。

**失败语义（统一抛 ApiException）**

| 场景 | 行为 |
| --- | --- |
| `code` 为空字符串 / 纯空白 | 抛 `ApiException`（请求前拦截，不浪费配额） |
| 接口返回错误码非 0（微信 `errcode` / 抖音 `err_no`） | 抛 `ApiException` |
| 响应缺少数据节点（微信 `phone_info` / 抖音密文 `data`） | 抛 `ApiException` |
| 结果缺少 `phoneNumber` / `purePhoneNumber` / `countryCode` | 抛 `ApiException` |
| 抖音未配置 `app_private_key` | 抛 `ApiException`（请求前拦截） |
| 抖音私钥无效、密文非法 base64、长度不匹配或解密失败 | 抛 `ApiException` |
| 抖音 `watermark.appid` 与当前小程序不符 | 抛 `ApiException` |

> 端到端测试：`tests/Providers/Wechat/PhoneTest.php`（成功换取、命中官方接口并携带 access_token、openid 透传 / 空 openid 不下发、两个便捷方法、空 code 前置拦截、errcode 非 0、缺 `phone_info`、字段不完整）、`tests/Providers/Douyin/PhoneTest.php`（现场生成 RSA 密钥对造真实密文：解密成功、命中官方接口并携带 `access-token`、client_token 用 `client_credential` 且命中缓存 / 清缓存后重取、两个便捷方法、空 code、未配私钥、`err_no` 非 0、空密文、watermark 不符 / 缺失、字段不完整、私钥不匹配）、`tests/Core/Crypto/RsaPkcs1Test.php`（真实密钥对 round-trip、多块分段、纯 Base64 私钥、空 / 非法私钥、非法 base64、长度不匹配、异密钥对、非 JSON 明文）、`tests/Union/PhoneByCodeTest.php`（微信 / 抖音分派成功 + openid 透传与忽略 + 不支持渠道抛错）、`tests/Union/PhoneByDecryptTest.php`（微信 / 飞书 / 企业微信 / 抖音分派成功 + 归一化结构 + 缓存 session_key 一站式 + 支付宝两入口抛错）、`tests/Union/PhoneByResponseTest.php`（支付宝 mini / mp / app 真实 AES 解密 + 验签通过 + 错误 sign 抛 ApiException + 非支付宝渠道抛 InvalidArgumentException）。

### 手机号收敛为 UnionPhone 值对象

若业务侧希望直接拿到强类型手机号对象（而非数组），可使用下列入口——它们与 `phoneByCode / phoneByDecrypt / phoneByUser / phoneByResponse` 一一对应，在「换取 + 归一化」之后进一步收敛为 `UnionPhone` 值对象，省去手写数组取值：

| 入口 | 对应数组入口 | 说明 |
| --- | --- | --- |
| `Union::phoneObjectByCode($channel, $code, ?$openId)` | `phoneByCode` | 微信 / 抖音 code 换手机号 |
| `Union::phoneObjectByDecrypt($channel, $encryptedData, $sessionKey, $iv)` | `phoneByDecrypt` | 显式 session_key 解密 |
| `Union::phoneObjectByUser($channel, $encryptedData, $iv, $openId)` | `phoneByUser` | 缓存 session_key 一站式解密 |
| `Union::phoneObjectByResponse($channel, $response, ?$sign)` | `phoneByResponse` | 支付宝 response + sign |

```php
$phone = $kernel->union()->phoneObjectByDecrypt(
    Channel::WechatMini, $encryptedData, $sessionKey, $iv
);
$phone->phoneNumber;     // 13800138000
$phone->purePhoneNumber; // 13800138000
$phone->countryCode;    // 86
$phone->toArray();      // ['phoneNumber' => ..., 'purePhoneNumber' => ..., 'countryCode' => ...]
```

> `UnionPhone` 与 {@see UnionUser}（用户资料）对称，是「统一敏感数据」三族（data / phone / userInfo）中 phone 路径的强类型收口。缺失字段兜底为空字符串，绝不抛异常。



### 统一加密用户资料入口（encryptedData，旧版路径）

若前端回传的是 `encryptedData` + `iv`（`<button open-type="getUserProfile">` 或 `getUserInfo` 回调的加密资料），可用与 `phoneByDecrypt()` 对称的统一入口：

| 渠道 | Union 入口 | 说明 |
| --- | --- | --- |
| 微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 | `Union::userInfoByDecrypt($channel, $encryptedData, $sessionKey, $iv)` | 显式传入 `session_key` 解密 |
| 同上 | `Union::userInfoByUser($channel, $encryptedData, $iv, $openId)` | 自动取用登录阶段托管的 `session_key`，无需手动传入 |

两方法返回各端用户资料数组：**原始字段全部保留**，并追加经 `Core\UserInfoNormalizer` 归一化的 snake_case canonical 键（`nickname` / `avatar` / `gender` / `city` / `province` / `country` / `language`），与登录 / profile 链路的 `UnionUser` 字段命名对齐，便于业务侧以统一结构消费。支付宝不在覆盖范围内（其无 `encryptedData` / `session_key`），调用会抛 `InvalidArgumentException`。

```php
// 显式 session_key
$profile = $kernel->union()->userInfoByDecrypt(
    Channel::WechatMini, $encryptedData, $sessionKey, $iv
);
$profile['nickName'];   // TestUser（原始字段保留）
$profile['nickname'];   // TestUser（归一化 canonical 键）

// 缓存 session_key（登录时已托管）
$profile = $kernel->union()->userInfoByUser(
    Channel::WechatMini, $encryptedData, $iv, $openId
);
```

### 加密用户资料收敛为 UnionUser 对象

若业务侧希望直接拿到与登录 / profile 链路**完全相同的 `UnionUser` 对象**（而非数组），可使用下列入口——它们在解密 + 归一化之后进一步收敛为对象，省去手写字段映射：

| 渠道 | Union 入口 | 说明 |
| --- | --- | --- |
| 微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 | `Union::userInfoObjectByDecrypt($channel, $encryptedData, $sessionKey, $iv, ?$openId, ?$unionId)` | 显式 `session_key`，返回 `UnionUser` |
| 同上 | `Union::userInfoObjectByUser($channel, $encryptedData, $iv, $openId, ?$unionId)` | 自动取用托管 `session_key`，返回 `UnionUser` |

```php
$user = $kernel->union()->userInfoObjectByDecrypt(
    Channel::WechatMini, $encryptedData, $sessionKey, $iv,
    $openId,   // 来自登录 code2session（加密资料明文不含 openid / unionid）
    $unionId,  // 来自开放平台（可选）
);
$user->nickname;  // TestUser
$user->avatar;    // https://...
$user->gender;    // '1'（透传，不做 male/female 枚举映射）
$user->toArray(); // 统一结构，与 Union::profile() 结果一致
```

> ⚠️ **gender 仅透传**：`UnionUser::fromDecryptedUserInfo()` 对 `gender` 只做「透传 + 类型归一化（int → 字符串）」，**不做** `0/1/2 → male/female` 枚举映射。各端 gender 编码并不一致，臆测映射会导致错误（参见 v1.34.0 设计取舍）。这与登录 / profile 链路的 `UnionUser::fromRaw()`（会把 int 映射成 male/female）行为不同，属有意为之。openId / unionId 加密资料明文里不存在，须由调用方显式传入。

> 端到端测试：`tests/Union/UserInfoByDecryptTest.php`（微信 / 飞书 / 企业微信 / 抖音分派成功 + 原始字段保留 + 归一化 canonical 键 + 缓存 session_key 一站式 + 支付宝两入口抛错）、`tests/Union/UserInfoObjectByDecryptTest.php`（两对象入口返回 `UnionUser` + 字段归一化 + 支付宝抛错）、`tests/Union/UnionUserFromDecryptedTest.php`（工厂：字段映射 / gender 透传 / 缺失 null / 空串 null / canonical 键兼容 / jsonSerialize）、`tests/Core/UserInfoNormalizerTest.php`（nickName/avatarUrl → nickname/avatar 归一化、snake_case 键兼容、缺失填空串 / gender 缺失为 null、gender 值原样透传）。

### 值对象可直接 JSON 序列化

`UnionPhone` 与 `UnionUser` 均实现 `JsonSerializable`，可直接 `json_encode()` 用于 API 响应，序列化结果等价于各自 `toArray()`（`UnionPhone` 仅核心三元组；`UnionUser` 不含 `raw` 原始字段、含 `extra` 扩展信息）：

```php
$phone = $kernel->union()->phoneObjectByDecrypt(Channel::WechatMini, $encryptedData, $sessionKey, $iv);
header('Content-Type: application/json');
echo json_encode(['phone' => $phone]);   // {"phone":{"phoneNumber":"...","purePhoneNumber":"...","countryCode":"..."}}

$user = $kernel->union()->userInfoObjectByDecrypt(Channel::WechatMini, $encryptedData, $sessionKey, $iv, $openId);
echo json_encode(['user' => $user]);     // 不含 raw，可直接下发前端（注意 extra 可能含敏感令牌，按需裁剪）
```

> 端到端测试：`tests/Union/UnionPhoneTest.php`（fromArray / 缺失兜底 / toArray / jsonSerialize）、`tests/Union/UnionUserFromDecryptedTest.php`（含 jsonSerialize 断言）。

### 从已登录 UnionUser 一键解密（桥接入口）

最常见的生产链路是：**先登录拿到 `UnionUser`（含 `channel` 与 `openId`），再用它解密手机号 / 用户资料**。`code2session` 阶段 SDK 已自动把 `session_key` 按 `openId` 托管到 `SessionKeyManager`，故解密时无需再传 `session_key`、`channel`、`openId`——本组桥接入口直接从 `UnionUser` 取回这些信息：

| 入口 | 说明 |
| --- | --- |
| `Union::phoneObjectForUser(UnionUser $user, $encryptedData, $iv)` | 复用 `phoneObjectByUser()`，从 `$user` 取 `channel` / `openId` |
| `Union::userInfoObjectForUser(UnionUser $user, $encryptedData, $iv)` | 复用 `userInfoObjectByUser()`，并从 `$user` 透传 `unionId`（若已携带） |

```php
$user  = Union::wechat()->mini($code);              // 登录即托管 session_key，拿到 UnionUser
$phone = Union::phoneObjectForUser($user, $encryptedData, $iv);   // 无需再传 channel/openId/session_key
$info  = Union::userInfoObjectForUser($user, $encryptedData, $iv); // 同样免重复传参
```

> 端到端测试：`tests/Union/UnionUserBridgeTest.php`（phone / userInfo 一键解密 + unionId 透传 + 不支持渠道抛错）。

## 各端 userInfo 字段差异对照表

「用户资料」有两条独立链路，字段语义**并不相同**，业务侧需按数据来源区分消费：

- **encryptedData 解密链路**：客户端回传加密密文，服务端用 `session_key` 解密。6 个对称端（微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信）的 `Decrypt::userInfo()` 均为**纯透传**，明文结构完全由客户端上报内容决定。支付宝无此入口（`Union::userInfoByDecrypt()` 对其抛 `InvalidArgumentException`）。
- **profile 拉取链路**：服务端调各端真实接口拿资料，经 `UnionUser::fromRaw()` 归一化。各端接口字段命名天差地别（camelCase / snake_case / 下划线 / 嵌套对象）。

### 1. encryptedData 解密：明文 raw 字段对照

| 字段 | 微信 | 企业微信 | 百度 | QQ | 抖音 | 飞书 |
| --- | :--: | :--: | :--: | :--: | :--: | :--: |
| `nickName` | ✅ | ✅ | ✅ | ✅ | ✅ | —（用 `name`） |
| `avatarUrl` | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `gender`（int） | ✅ `1` | ✅ `1` | ✅ `1` | ✅ `1` | ✅ `1` | — |
| `language` | ✅ | ✅ | ✅ | — | — | — |
| `city` / `province` / `country` | ✅ | ✅ | ✅ | — | — | — |
| `watermark{appid,timestamp}` | ✅ | ✅（appId 非 corpid） | ✅ | ✅ | ✅ | —（无） |
| `name` / `openId`（独有） | — | — | — | — | — | ✅ `name`+`openId` |

> - 微信 / 企业微信 / 百度逐字段一致（除语言 / 地区），均兼容微信 `getUserInfo`。QQ / 抖音仅含 `nickName` / `avatarUrl` / `gender` / `watermark`。
> - **飞书是异类**：key/iv 用 hex 编码、无 `watermark`、明文是 `name`+`openId`（无头像 / 性别 / 地区）。
> - **企业微信** `watermark.appid` 是**小程序 appId 而非 corpid**（v1.30.0 修过 bug，用 corpid 校验会误判）。

### 2. profile 拉取：raw → canonical 映射对照

| 平台 | 昵称 raw | 头像 raw | gender raw | unionId 来源 | raw 包封层级 |
| --- | --- | --- | --- | --- | --- |
| 微信 mp / h5 / app / pc | `nickname` | `headimgurl` | `sex`(int)→`male` | `unionid` | `toArray()`（含协议噪声） |
| 微信小程序 | `unionId` / `unionid`（客户端 raw） | `avatarUrl` | — | 客户端 raw | 直接 raw |
| 抖音 | `nick_name` | `avatar` | `gender`(`男`) | `union_id` | `array('data')` |
| QQ | `nickname` | `figureurl_qq_2` | `gender`(`男`) | —（硬编码空） | `toArray()` |
| 百度 | `nickname` | `headimgurl` | `sex`(int)→`male` | —（硬编码空） | `array('data')` |
| 企业微信 | `name` | `avatar` | — | —（硬编码空） | `toArray()` |
| 支付宝 | `nick_name` | `avatar` | — | —（硬编码空） | `array('alipay_user_info_share_response')` |
| 钉钉 | `name` | `avatar` | — | raw 有但被丢弃 | `array('result')` |
| 飞书 | `name.zh_cn`（嵌套拍平为 `nick_name`） | `avatar.avatar_origin`（拍平为 `avatar_url`） | — | `union_id` | `array('data')` |

> 飞书是唯一需 `normalize()` 拍平嵌套对象的端（`contact/v3/users` 返回 `name{zh_cn,en_us}` / `avatar{origin,240,72}`）；若不拍平，昵称与头像会被 `str()` 因「非字符串」静默跳过而全丢。

### 3. gender 两条链路结果不一致（重点坑）

| 场景 | 输入 | profile 链路 `fromRaw()` | decrypt 链路 `fromDecryptedUserInfo()` |
| --- | --- | --- | --- |
| 百度 / 微信 profile `sex=1` | `1`（int） | `'male'` | — |
| decrypt `gender=1` | `1`（int） | — | `'1'` |
| 字符串 `'1'` | `'1'` | `'1'`（不映射） | `'1'` |

> 同一份 `gender=1` 走两条链路得到 `'male'` 与 `'1'`，这是**设计取舍而非 bug**：profile 链路 `UnionUser::str()` 对 int 枚举映射；decrypt 链路 `normalizeGender()` 仅「透传 + int→string」，**绝不**枚举映射（避免臆测各端 gender 编码，参见 v1.34.0）。消费时务必按数据来源区分。

### 4. unionId 供给能力

| 平台 | 能否拿到 unionId | 备注 |
| --- | :--: | --- |
| 微信全系 | ✅ | raw `unionid` / 小程序 raw `unionId` |
| 抖音 | ✅ | `union_id` |
| 飞书 | ✅ | `union_id` |
| QQ / 百度 / 支付宝 / 企业微信 | ❌ | 接口本不返回，硬编码空 |
| 钉钉 | ⚠️ 接口返回但被丢弃 | raw 含 `unionid`，`DingtalkUserAdapter` 写死 `''`，需从 `$user->raw['unionid']` 取 |

### 5. 已知差异 / 坑汇总

- **`language` 对象链路丢失**：decrypt 数组入口（`userInfoByDecrypt`）返回含 `language`，但对象入口（`userInfoObjectByDecrypt` → `UnionUser`）因 `UnionUser` 无该属性而**丢弃**，只能从 `$user->raw['language']` 取。
- **钉钉 `unionid` 静默丢弃**（见上表）。
- **`sex` 优先于 `gender`**：映射列表 `['sex','gender']`，若 raw 同时含两键，`sex` 胜出。
- **字符串型数字不映射**：raw 为 `'1'`（字符串）时直接透传，拿不到 `'male'`。企业微信 `/user/get` 真实 gender 即字符串 `"1"`/`"2"`，一旦补该字段会走「字符串透传」，与微信 int 映射结果不同。
- **飞书 profile 必须拍平**：见 §2 注。
- **raw 包封层级三种**：`array('data')` / `array('result')` / `array('alipay_..._response')` / `toArray()`（混入 `errcode`/`ret`/`msg` 等协议噪声），遍历 `raw` 时需留意。

## 支付回调（Notify）统一入口

除各 Provider 自带的 `notify()`（含签名验签）外，Union 提供跨端统一的回调**归一化**入口，
适合「一个回调控制器按渠道分发」的场景，与统一登录 / 支付 / 解密入口对称：

```php
use Kode\MiniApp\Union\Union;

$union = $kernel->union();

// 按渠道取通知适配器：微信(小程序/公众号/PC/App/开放平台) / 企业微信 / 支付宝 / 抖音 / 百度 / QQ 均已支持
$notify = $union->wechat()->notify();   // 或 alipay() / baidu() / douyin() / qq() / wechatWork()

// $raw 为业务侧已将 XML / 表单参数解析后的关联数组
$payload = $notify->decode($raw);

// 归一化字段随渠道不同：
//   微信/QQ：out_trade_no, transaction_id, total_fee, openid, result_code, raw
//   支付宝  ：out_trade_no, trade_no, total_amount, trade_status, raw
//   抖音/百度：out_trade_no, trade_no, result_code|status, raw
//   企业微信：event_type, raw
$outTradeNo = $payload['out_trade_no'];
```

> ⚠️ `Union::notify()->decode()` **仅做字段归一化，不验签**。微信 / 支付宝 / 抖音 / 百度 / 企业微信
> 的回调签名验签请使用各 Provider 自带的 `notify()`；QQ 回调由 `Qq\Modules\Notify` 内部完成
> XML+MD5 验签，建议直接用 `$kernel->qq()->app()->notify()`。业务侧务必在 `decode()` 之前完成
> 签名校验，避免伪造回调。

### 回调渠道支持矩阵

| 渠道 | Union notify 归一化 | Provider 级验签 | 说明 |
| --- | --- | --- | --- |
| 微信 | ✅ | ✅ | 验签走 Provider `notify()`（api_v3_key） |
| 微信开放平台 | ✅ | ✅ | 同微信（代授权方） |
| 企业微信 | ✅ | ✅ | 验签走 Provider `notify()` |
| 支付宝 | ✅ | ✅ | 验签走 Provider `notify()`（RSA2） |
| 抖音 | ✅ | ✅ | 验签走 Provider `notify()` |
| 百度 | ✅ | ✅ | 验签走 Provider `notify()` |
| QQ | ✅ | ✅ | 验签内置 `Qq\Modules\Notify`（XML+MD5） |
| 钉钉 / 飞书 | — | — | 无消费者支付回调 |

## 支付能力归属：miniapp 只管身份，收钱交给 kode/pays

两个包的关注点完全不同，且互不依赖：

- **kode/miniapp（本包）=「你是谁」**：OAuth 授权、拿 `openid` / `unionid` / `session_key` / `access_token`、JS-SDK 票据。哪怕不收一分钱（只做登录 / 授权）也用得到本包。
- **kode/pays =「收钱」**：下单、签名、调起支付、异步回调验签、退款、分账、转账、对账、沙箱、多渠道聚合。一个纯 Web 后端只接 Stripe / PayPal 也用得到它，跟本包毫无关系。

因此**本包不应内置支付业务逻辑，也不保留任何内置支付实现**。本包在「支付」这件事上只做两件事，其余全部交给 kode/pays：

1. **产出付款人身份**：`UnionUser`（含 `openId`），这是支付的唯一可信付款人来源；
2. **翻译并桥接**：把 Kernel 凭证 + `UnionUser` 翻译为 kode/pays 的原生形参后下单（见下方 `PaysBridge`）。

> ⚠️ **2.0 破坏性变更**：自 v2.0 起，所有内置支付实现（`Providers/*/Modules/Pay.php`、
> `Union/Channels/*/PayAdapter.php`、各端 `QqNotifyAdapter` 等）已**彻底移除**，kode/pays
> 成为**唯一**支付路径，且为本包 `composer require` 级别的**硬依赖**。调用 `Union::pay()` /
> `Union::notify()` 前必须先 `composer require kode/pays`；未安装时这两个方法会抛清晰异常，
> 引导你先安装 kode/pays，而不会再静默回退到任何内置实现。

### 命名统一（核心体验）

本包对外暴露的支付方法名**与 kode/pays 网关契约完全一致**（pays 包本身不改任何命名）：

| 能力 | 本包 / pays 统一方法名 |
| --- | --- |
| 下单 | `createOrder(array $order, ?UnionUser $user = null)` |
| 查询订单 | `queryOrder(string $orderId)` |
| 申请退款 | `refund(array $params)` |
| 查询退款 | `queryRefund(string $refundId)` |
| 关闭订单 | `closeOrder(string $orderId)` |
| 回调验签 | `verifyNotify(array $payload, array $headers = []): array` |

`?UnionUser $user` 是相对 pays 的「超集」参数（pays 同名方法无此参数），用于把登录得到的付款人身份
自动注入下单；其余方法名、参数顺序与 pays 一模一样，因此业务侧调用方式与 kode/pays 完全一致。

**业务侧只需 `composer require kode/pays`**，随后 `Union::pay()->createOrder(...)` / `Union::notify()->decode(...)`
即可完成下单与回调验签，付款人 `openid` / `buyer_id` 由桥接自动注入：

```php
// 装了 kode/pays：下单 + 回调验签都走 pays（全生命周期统一）
$user  = Union::wechat()->mini($code);
$order = Union::wechat()->pay()->createOrder($payload, $user);            // openid 自动注入
$data  = Union::wechat()->notify()->decode($payload, $headers);           // 验签 + 解密，返回可信业务数组
```

### 本包 与 kode/pays 的边界（单向、可选胶水）

| 层 | 职责 | 是否拿得到 openid |
| --- | --- | --- |
| **本包 miniapp** | 登录 + 平台身份（OAuth / code2session）、openid / unionid、用户体系；产出 `UnionUser` 并经 `PaysBridge` 翻译给 pays | ✅ 登录时即可拿到，是唯一 openid 来源 |
| **kode/pays** | 支付编排：下单、回调验签、退款、对账、分账、转账、沙箱、多渠道聚合 | ❌ 不处理登录、不知道 miniapp 的存在；付款人只是其 `createOrder(array $params)` 里的一个原生字段（`openid` / `buyer_id`） |

- **kode/pays 不依赖 miniapp**：它的 `createOrder(array $params)` 签名里没有任何 miniapp 类型，付款人只是一个字符串字段，因此对纯 Web / Stripe 后端零耦合。
- **miniapp 在 composer 里把 kode/pays 列为 `require`**（2.0 起为硬依赖）：未安装时 `Union::pay()` / `Union::notify()` 会抛清晰异常，引导先 `composer require kode/pays`。
- **桥接是单向胶水、落在 miniapp 一侧**：`PaysBridge` 知道 kode/pays，kode/pays 不知道 miniapp。

> ✅ kode/pays 的微信网关在 JSAPI / 小程序下单时**强制要求 `openid`**，且源码注释明确
> 「openid 来自公众号 / 小程序 OAuth 授权，**如 kode/miniapp**」。这从侧面印证了上述边界：
> pays 负责验「有没有 openid」并拿它去下单，但 openid 必须由本包登录提供。

### 付款人身份如何桥接（关键）

pays 的 `createOrder` 不接受付款人对象，所以**由 `PaysBridge` 把 `UnionUser.openId` 映射进 `$params` 的原生付款人字段**再下单：

- 微信 / QQ → `$params['openid']`（服务商模式由 pays config 的 `sub_appid` 自动落到子商户，桥接只注入 `openid`）；
- 支付宝 → `$params['buyer_id']`；
- `$order` 中已显式提供该字段时不会被覆盖；传 `null` 用户则需业务侧自行提供。

桥接还做了**渠道守卫**：付款人必须来自与本次支付同一平台（跨平台 openid 不互通），否则直接抛 `InvalidArgumentException`——这同时根除旧「查库取 openid 再拼」方案中「没在公众号登录关联的用户无法支付」的痛点：没登录就没有 `UnionUser`，桥接 fail-fast。

```php
// 推荐路径：本包登录拿身份 → 装 pays 后 pay() 走 pays（openid 由桥接注入）
// 步骤 1：composer require kode/pays（2.0 起支付完全由 kode/pays 承载）
$user  = Union::wechat()->mini($code);            // ① 身份：本包登录
$order = Union::wechat()->pay()->createOrder([   // ② 收钱：kode/pays
    'out_trade_no' => 'ORDER_' . time(),
    'description'  => '商品',
    'amount'       => ['total' => 100],           // 分（V3 结构）
], $user);
```

### kode/pays 桥接（首选支付入口）

`PaysBridge` 实现与 `PayAdapter` 完全相同的 `createOrder(array, ?UnionUser):array` 契约、返回平台原始数组，因此业务侧调用方式零改动即可从内置适配器切换到 kode/pays。

> ⚠️ `kode/pays` 为**硬依赖**：业务侧必须先 `composer require kode/pays`。未安装时
> `Union::pay()` / `Union::notify()` 会抛清晰异常，引导先安装；不再有任何内置 fallback。

```php
// 1) 一行调用：用 Kernel 中已配置的凭证自动拼装 kode/pays config
//    openid 来自本包登录（user: $user 自动注入），kode/pays 不会替你登录
$user  = Union::wechat()->mini($code);
$order = $kernel->union()->wechat()->pay()->createOrder([
    'out_trade_no' => 'ORDER_' . time(),
    'description'  => '商品',
    'amount'       => ['total' => 100],
], $user);

// 2) 自定义凭证来源（单独维护 kode/pays config，或覆盖百度 / 企业微信等默认 resolver 未覆盖的渠道时）
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;

$pay = PaysBridge::adapter(Channel::WechatMini, static fn () => [
    'app_id'  => 'wx...',
    'mch_id'  => '...',
    'api_key' => '...',         // 微信 v2 商户密钥
]);
$order = $pay->createOrder([/* ... */], $user);

// 运行时判断 kode/pays 是否就绪
if (PaysBridge::available()) {
    $pay = $kernel->union()->wechat()->pay();
}
```

桥接器做的关键字段映射（默认 `kernelResolver`）：

| miniapp 字段 | kode/pays 字段 | 说明 |
| --- | --- | --- |
| `app_id` / `mch_id` | `app_id` / `mch_id` | 同名透传 |
| `key` | `api_key` | 微信 v2 商户密钥，字段名不同 |
| `api_v3_key` / `cert_path` / `key_path` / `mch_serial_no` | 同名 | 仅非空时透传 |
| `app_id` / `private_key` / `public_key` / `sandbox` | 同名 | 支付宝 |

覆盖渠道：默认 resolver 支持**微信 / 支付宝 / 抖音 / QQ**；百度 / 企业微信等 kode/pays 暂未覆盖的渠道，请使用 `PaysBridge::adapter()` 注入自定义 resolver（`PaysBridge::kernelResolver()` 会抛清晰引导）。

## 能力发现与配置契约

为降低接入心智负担，Union 提供了**运行时能力自检**与**配置契约校验**两套机制：开发者无需熟读各平台文档，即可在启动 / 接入前确认「某渠道支持什么、还缺哪些配置」。

### 1. 渠道能力发现（Capability Discovery）

`Channel` 枚举为每个渠道声明了它**实际支持**的能力（`ChannelFeature`：`Login` / `Pay` / `Notify` / `User` / `Decrypt`），并如实反映当前适配器覆盖——例如微信 **H5 / PC 已支持支付**（MWEB / NATIVE，无需 openid，故不声明 `Decrypt`）；微信开放平台（第三方平台）无独立支付适配器（其支付由服务商模式的 `sp_mchid` / `sub_mchid` 承载），故 `Pay` 为 `false`。

```php
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Contracts\ChannelFeature;

// 单个渠道
$info = Union::capabilities(Channel::WechatMini);
$info->supports(ChannelFeature::Pay);     // true
$info->supports(ChannelFeature::Decrypt); // true
$info->toArray();
// => [
//      'channel'         => 'wechat_mini',
//      'label'           => '微信小程序',
//      'features'        => ['login','pay','notify','user','decrypt'],
//      'required_config' => ['app_id','mch_id','key_path','mch_serial_no'],
//    ]

// 对照：微信 H5（MWEB）已支持支付，但无需 openid，故不声明 Decrypt
$h5 = Union::capabilities(Channel::WechatH5);
$h5->supports(ChannelFeature::Pay);     // true
$h5->supports(ChannelFeature::Decrypt); // false
```

枚举上也可直接查询，无需实例化 Union：

```php
Channel::WechatMini->supports(ChannelFeature::Pay); // true
Channel::WechatH5->features();                      // [Login, Notify, User]
Channel::WechatMini->providerKey();                 // 'wechat'（回溯 Provider 配置）
```

### 2. 配置契约（Config Contract）

每个平台 `Config` 声明两类必填键，缺失时给出**清晰清单**而非运行时才暴露的诡异错误：

- `requiredKeys()`：平台级必填（任一能力都需提供）。如微信 `['app_id']`、支付宝 `['app_id','private_key','public_key']`。
- `requiredKeysFor(ChannelFeature::Pay)`：启用某能力时的**额外**必填。如微信支付还需 `['mch_id','key_path','mch_serial_no']`。

```php
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Contracts\ChannelFeature;

$config = $kernel->provider('wechat')->config();

// 运行前自检，缺键直接抛 ConfigException 并列出缺失项
$config->validate();                       // 校验 app_id
$config->validateFeature(ChannelFeature::Pay); // 校验 mch_id / key_path / mch_serial_no

// 或只读查询（不抛异常），用于生成接入检查清单
$missing = array_diff(
    $config->requiredKeysFor(ChannelFeature::Pay),
    array_keys($config->all()),
);
// $missing 即为还需补充的配置键
```

`Union::capabilities()` 返回的 `required_config` 已将上述两类键**合并去重**，是「接入某渠道需要配哪些键」的一站式答案。

> 说明：本包只负责登录与平台身份，**不内置任何支付实现**。因此微信渠道 `requiredKeysFor(Pay)` 仅含直连商户 JSAPI 所需的 `mch_id` / `key_path` / `mch_serial_no` 等键；服务商 / 开放平台统一支付所需的 `sub_mchid` 等，由 `kode/pays` 各自在网关层约束，不在此处展开。

## 自定义适配器（业务扩展）

```php
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;
use Kode\MiniApp\Union\Channel;

class MyLoginAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatMini;
    }

    public function authenticate(array $payload): UnionUser
    {
        // 自定义登录逻辑
        return UnionUser::fromRaw(
            channel: Channel::WechatMini,
            openId:  $payload['openid'] ?? '',
            raw:     $payload,
        );
    }
}

$kernel->union()->registerLoginAdapter(new MyLoginAdapter());
```

## 底层 vs 统一入口

| 维度 | 底层 Provider / App | Union 统一入口 |
|------|--------------------|-----------------|
| 适用对象 | 框架开发者、扩展定制 | 业务开发者（**99% 场景**） |
| 能力粒度 | 30+ 模块细粒度 | 场景登录 + 4 大能力 + 透传 Provider |
| 学习成本 | 高（需了解每个模块） | 极低（几行代码搞定） |
| 跨平台切换 | 需重写业务 | 无缝切换 |
| UnionID 处理 | 手动 | 自动 |
| 静态调用 | 不支持 | 支持 `Union::wechat()` |

> **设计哲学**：底层 Provider/App 是 SDK 的"零件库"，Union 是面向业务场景的"成品工具"。
> 业务侧 99% 的需求都可以用 Union 解决，仅在需要极细粒度控制时才用底层 Provider。

## 完整调用示例

```php
use Kode\MiniApp\Union\Union;

// ===== 1. 跨平台登录 =====
$user1 = Union::wechat()->mini('JS_CODE');           // 微信小程序
$user2 = Union::wechat()->mp('CODE');                // 公众号
$user3 = Union::wechat()->pc('PC_CODE');             // PC 扫码
$user4 = Union::wechat()->h5('H5_CODE');             // H5
$user5 = Union::wechat()->app('APP_CODE');           // 移动 App

$user6 = Union::alipay()->mini('AUTH_CODE');         // 支付宝小程序
$user7 = Union::alipay()->mp('CODE');                // 支付宝生活号
$user8 = Union::alipay()->login(['code' => $code, 'channel' => 'alipay_app']);  // 支付宝 App

$user9  = Union::douyin()->mini('CODE');             // 抖音小程序
$user10 = Union::baidu()->mini('CODE');              // 百度小程序
$user11 = Union::qq()->mini('CODE');                 // QQ 小程序
$user12 = Union::work()->login('CODE');              // 企业微信
$user13 = Union::dingtalk()->mini('CODE');           // 钉钉
$user14 = Union::lark()->mini('CODE');               // 飞书

// ===== 2. 跨平台支付 =====
$order1 = Union::wechat()->pay()->createOrder([
    'out_trade_no' => 'O001',
    'body'         => '商品',
    'total_fee'    => 100,
    'openid'       => $user1->openId,
]);
$order2 = Union::alipay()->pay()->createOrder([...]);
$order3 = Union::work()->pay()->createOrder([...]);

// ===== 3. 跨平台回调 =====
$data1 = Union::wechat()->notify()->decode($payload, $headers);
$data2 = Union::alipay()->notify()->decode($payload, $headers);

// ===== 4. 跨平台用户资料 =====
// 公众号 / H5 自动解析 mp access_token；小程序传客户端上报数据；App / PC 传登录 token
$user1 = Union::wechat()->user($openId, [], 'mp');
$user2 = Union::alipay()->user($openId, ['access_token' => $token]);

// ===== 5. 细粒度访问（30+ 模块） =====
$app = Union::wechat()->appInstance();
$app->message()->sendSubscribe($openId, $templateId, [...]);
$app->media()->upload('image', '/path/to/image.jpg');
$app->menu()->create([...]);
$app->jssdk()->config($url, ['chooseImage']);
```

## 架构设计

```
Union 静态门面（__callStatic 调度）
  ├── wechat()       → WechatUnion       → WechatProvider      → WechatApp (30+ 模块)
  ├── alipay()       → AlipayUnion       → AlipayProvider      → AlipayApp
  ├── douyin()       → DouyinUnion       → DouyinProvider      → DouyinApp
  ├── baidu()        → BaiduUnion        → BaiduProvider       → BaiduApp
  ├── qq()           → QqUnion           → QqProvider          → QqApp
  ├── wechatWork()   → WechatWorkUnion   → WechatWorkProvider  → WechatWorkApp
  ├── dingtalk()     → DingtalkUnion     → DingtalkProvider    → DingtalkApp
  ├── lark()         → LarkUnion         → LarkProvider        → LarkApp
  └── wechatOpen()   → WechatOpenPlatformUnion → WechatOpenProvider → WechatOpenApp
```

**统一账号体系**：

```
用户在小程序登录       用户在公众号登录       用户在 PC 扫码       用户在 App 登录
       ↓                     ↓                    ↓                    ↓
   jscode2session        OAuth 网页授权        扫码回调            App 授权回调
       ↓                     ↓                    ↓                    ↓
   Union::wechat()->mini   Union::wechat()->mp   Union::wechat()->pc  Union::wechat()->app
       ↓                     ↓                    ↓                    ↓
       └─────────────────────┴────────────────────┴────────────────────┘
                                       ↓
                                 统一 UnionUser
                                  ├─ unionId: u001 (跨端相同)
                                  ├─ openId:  wx_xxx (平台内唯一)
                                  ├─ channel: wechat_mini / wechat_mp / wechat_pc / wechat_app
                                  ├─ nickname, avatar, ...
                                  └─ raw / extra (平台原始数据)
```
