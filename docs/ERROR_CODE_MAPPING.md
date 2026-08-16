# 统一错误码归一化（kode/pays → miniapp ApiException）

> 适用版本：kode/miniapp **v2.0.22+**，kode/pays **v2.17.0+**
> 配套可执行契约测试：`tests/Union/Bridge/PaysBridgeErrorNormalizationTest.php`

---

## 1. 为什么需要归一化

kode/pays 在「参数校验 / 签名 / 报文拼装 / 响应解析」阶段抛出自己的
`Kode\Pays\Core\PayException`；而 miniapp 约定「平台业务错误统一为
`Kode\MiniApp\Exceptions\ApiException`（无静默成功）」。

若不归一，业务侧需同时捕获两套异常体系。为此 `PaysBridge::invokeGateway()`
成为**桥接层唯一出口**，把 `PayException` 包裹为 `ApiException`，业务侧只需
`catch (ApiException $e)` 一种类型即可覆盖身份层与支付层全部业务错误。

---

## 2. 两层错误码模型

| 层 | 来源 | 落在哪里 | 含义 |
|----|------|----------|------|
| **内部码** | `PayException::ERROR_*`（1000–1008） | `ApiException::errorCode()` | pays SDK 自身的错误**分类**（参数/签名/网关/配置…） |
| **平台原始码** | 微信 `err_code` / V3 `code`、QQ `errcode` 等 | `ApiException::payload()['gateway_code']` | 支付渠道返回的业务错误**明细**（如 `NOAUTH`、`AMOUNT_LIMIT`） |

> 关键点：**内部码与平台原始码不是「翻译关系」，而是「并存关系」**。
> 内部码告诉你「是哪一类错」，平台原始码告诉你「渠道具体为什么错」。

---

## 3. 字段映射表（PayException → ApiException）

`PaysBridge::invokeGateway($fn, Channel, $capability)` 抛出的 `ApiException`：

| PayException 字段 | → ApiException 字段 | 说明 |
|-------------------|---------------------|------|
| `getMessage()` | `getMessage()` | 保留原错误信息（含平台 `err_code_des` 时即为此值） |
| `getCode()`（= `ERROR_*`） | `errorCode()` / `getCode()` | **1:1 直传**（int）。见 §4 表 |
| `getGatewayCode()` | `payload()['gateway_code']` | 平台原始码，原样透传，不翻译不丢弃 |
| `getGatewayMessage()` | `payload()['gateway_message']` | 平台原始信息（见 §5 注意） |
| 本身 | `getPrevious()` | 原 `PayException` 作为异常链完整保留，可回溯 |
| — | `platform()` | **恒为 `null`**（支付层不绑定身份平台） |
| — | `action()` | `"支付[<渠道label>]<capability>"`，如 `支付[微信小程序]transferSingle` |
| — | `payload()['gateway']` / `['capability']` | 渠道 label 与能力名，便于日志定位 |

---

## 4. 内部错误码表（ERROR_* → errorCode 1:1）

| 常量 | 码 | 语义 | 典型触发点（微信网关） |
|------|----|------|------------------------|
| `ERROR_UNKNOWN` | 1000 | 未知错误 | 兜底 |
| `ERROR_CONFIG` | 1001 | 配置错误 | 缺 `app_id/app_secret`、`serial_no`、`api_v3_key`、`private_key` |
| `ERROR_NETWORK` | 1002 | 网络请求失败 | HTTP 层异常（经 `Pay::setHttpClient` 注入的客户端） |
| `ERROR_SIGN` | 1003 | 签名验证失败 | 微信响应 `sign` 验签不通过（`parseResponse`） |
| `ERROR_PARAM` | 1004 | 业务参数错误 | `openid` 缺失、`total_num<3`（裂变红包）、`recipient` 必填项不全 |
| `ERROR_GATEWAY` | 1005 | 网关业务错误 | 微信 `return_code=FAIL` / `result_code=FAIL`（携带 `gateway_code`） |
| `ERROR_ORDER_NOT_FOUND` | 1006 | 订单不存在 | `out_trade_no` 与 `transaction_id` 均缺失等 |
| `ERROR_REFUND` | 1007 | 退款失败 | 退款业务逻辑失败 |
| `ERROR_METHOD_NOT_SUPPORTED` | 1008 | 网关不支持该方法 | `settleToPayout` / `pauseSubscription` / `cancelRefund` 等未实现能力 |

> `invokeGateway` **只**归一化 `PayException`。桥接层自身错误（未装 kode/pays、
> 渠道不支持某能力、付款人渠道不匹配等抛出的 `RuntimeException` / `InvalidArgumentException`）
> 仍按原样抛出，不被包裹。

---

## 5. 平台原始码透传细节（重要）

不同渠道把「原始码」放在不同字段，归一后位置也不同：

### 微信 V2（`WechatPayGateway::parseResponse`）
- 通信失败：`return_code=FAIL` → `gateway_code = return_code`，`message = return_msg`
- 业务失败：`result_code=FAIL` → `gateway_code = err_code`，`message = err_code_des`
- **注意**：该路径不传 `gateway_message`（第 3 构造参数），故
  `payload['gateway_message']` 为 `null`，人类可读的失败原因在 **`getMessage()`** 里。

### 微信 V3（`WechatPayV3Gateway`）
- JSON 含 `code` 字段 → `gateway_code = code`，`message = message`（如 `PARAM_ERROR`、`INVALID_REQUEST`）
- `gateway_message` 同样为 `null`。

### 直接构造（代码显式传递时）
`PayException::gatewayError('msg', 'AMOUNT_LIMIT', '金额超限')` 会把第三个参数
填入 `gateway_message`，此时 `payload['gateway_code']='AMOUNT_LIMIT'`、
`payload['gateway_message']='金额超限'` 两者俱在。

### 常见微信原始码（原样出现在 `payload['gateway_code']`）
`NOAUTH`（未开通/无权限）、`AMOUNT_LIMIT`（金额超限）、`NOTENOUGH`（余额不足）、
`ORDERPAID`（订单已支付）、`OUT_TRADE_NO_USED`（商户单号重复）、`SYSTEMERROR`（系统错误）、
`APPID_MCHID_NOT_MATCH`（appid 与 mch_id 不匹配）等。完整清单以微信支付官方文档为准。

---

## 6. 消费方示例

```php
use Kode\MiniApp\Exceptions\ApiException;

try {
    $adapter->transferSingle([/* ... */]);
} catch (ApiException $e) {
    // 1) 内部分类：决定「重试 / 换参数 / 报配置」
    match ($e->errorCode()) {
        \Kode\Pays\Core\PayException::ERROR_PARAM  => /* 校验入参后重试 */,
        \Kode\Pays\Core\PayException::ERROR_CONFIG => /* 检查商户配置 */,
        \Kode\Pays\Core\PayException::ERROR_GATEWAY => /* 看渠道原始码 */,
        default => /* 上抛或记录 */,
    };

    // 2) 平台原始码：给用户/客服看的精确原因
    $rawCode = $e->payload()['gateway_code'] ?? null;   // 如 'AMOUNT_LIMIT'
    $rawMsg  = $e->getMessage();                         // 含 err_code_des

    // 3) 异常链回溯（如需底层堆栈）
    $cause = $e->getPrevious(); // 恒为 Kode\Pays\Core\PayException
}
```

> ⚠️ 注意：`ApiException` 上的 `isTokenInvalid()` / `isRateLimited()` /
> `isRetryable()` 与 `TOKEN_INVALID_CODES` / `RATE_LIMITED_CODES` / `RETRYABLE_CODES`
> 常量，是针对**身份层平台码**（微信 `40001`、钉钉 `88`、飞书 `99991663` 等
> `access_token` 相关码）设计的，**不适用于**本文件所述的 pays 内部 ERROR_*
> 码。支付层重试判定请基于 §4 内部码 + §5 平台原始码自行实现。
