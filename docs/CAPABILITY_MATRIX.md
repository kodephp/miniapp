# 能力矩阵（Capability Matrix）

> 本文档是 kode/miniapp **2.0 支付提取 + kode/pays 桥接** 的能力落地说明，与代码严格一致（零漂移）。
> 基础能力（登录 / 资料 / 解密 / 手机号 / 支付 / 回调）见 [README · 能力支持矩阵](../README.md#能力支持矩阵)。

## 1. 事实源（SoT）与零漂移保证

所有「高级支付能力」开关由 `PaysBridgePayAdapter::paymentCapabilities()` 产出，其判定逻辑为：

```php
gatewaySupports($feature) === method_exists(GatewayFactory::getGatewayClass($channel), $feature)
```

即**能力声明 = kode/pays 真实网关类的实现现状**，不存在「声明支持却未实现」的漂移。该契约由 `tests/Union/Bridge/PaysBridgeCapabilityMatrixConsistencyTest.php` 守护：

- 以 `GatewayFactory::getGatewayClass($channel)` + 类级 `method_exists` 为唯一事实源，**独立重算**每个渠道的能力矩阵；
- 断言与 `paymentCapabilities()` 逐键严格一致，任何网关实现变更导致的漂移都会被捕获。

> ⚠️ 能力门基线：`miniapp` 依赖 `kode/pays ^2.0`，当前 vendor 锁定 **v2.17.0**。判断能力一律以 `vendor/kode/pays`（2.17.0）为准，不以本地 `pay_open` 更高版本推测。

## 2. 高级支付能力矩阵（支持性）

10 项高级能力 × 4 个已桥接支付渠道（其余 B 端平台无消费者支付场景，标记为「—」属设计预期）：

| 能力（capability） | 方法（kode/pays 网关） | 微信 V2 | 支付宝 | 抖音 | QQ |
|--------------------|------------------------|:-------:|:------:|:----:|:---:|
| 分账 `profit_sharing` | `createProfitSharing` | ✅ | ✅ | ✅ | ❌ |
| 转账 `transfer` | `singleTransfer` | ✅ | ✅ | ❌ | ❌ |
| 对账 `reconciliation` | `downloadBill` | ✅ | ✅ | ❌ | ❌ |
| 红包 `red_packet` | `sendRedPacket` | ✅ | ✅ | ❌ | ❌ |
| 订阅 `subscription` | `createSubscription` | ✅ | ✅ | ❌ | ❌ |
| 余额 `balance` | `queryBalance` | ❌ | ✅ | ❌ | ❌ |
| 结算 `settlement` | `settleToWallet` | ✅ | ✅ | ❌ | ❌ |
| 个人收款 `personal_receive` | `createQrCode` | ✅ | ✅ | ❌ | ❌ |
| Webhook 事件 `webhook` | `verifyWebhook` | ✅ | ✅ | ✅ | ✅ |
| 退款 `refund` | `refund` | ✅ | ✅ | ✅ | ✅ |

说明：

- **微信 V2 无余额**：`WechatPayGateway`（V2）未实现 `BalanceCapableInterface`，故 `supportsBalance() === false`，余额能力仅支付宝支持。
- **抖音支持分账 + 退款 + Webhook**：抖音网关实现 `createProfitSharing` / `refund` / `verifyWebhook`，下单 / 查单 / 退款 / 查退款 / 分账的真实签名链均为 MD5+salt（已 e2e 验证）；其余高级能力为 `false`。
- **QQ 支持退款 + Webhook**：QQ 网关实现 `refund` / `verifyWebhook`，下单 / 查单 / 关单 / 退款走 V3 RSA-SHA256 签名链（复用微信 V3 特质，已 e2e 验证）；但分账 / 转账等其余高级能力为 `false`。
- **微信 V3 独立网关**：`Channel::WechatMini` 经 `GatewayFactory` 解析到 **V2 网关**；V3（`wechat_v3`，含 `decryptResource` / `verifyWebhook` / `batchTransfer`）通过 `GatewayFactory::create('wechat_v3', $config)` 显式取得。Webhook 验签与入站解密均走 V3 路径。

## 3. 跨渠道真实网关签名链 e2e 验证总览

下表标注各能力在对应渠道的**真实网关签名链是否已 e2e 验证**（✅ 已验证 / ❌ 不支持 / ⏳ 能力支持但 e2e 待补）。验证用例均不触网：以 `FakeHttpClient` 拦截出站请求，并用运行时商户密钥独立复核签名（微信 V2 `Signer::md5` / 支付宝 `Signer::rsa2` `openssl_verify(SHA256)`），V3 用 `Encryptor::aesGcmDecrypt` + 自有 RSA 密钥对验签。

| 能力 | 微信 V2（MD5） | 支付宝（RSA2） | 抖音 | QQ |
|------|:-------------:|:--------------:|:----:|:---:|
| 下单 `createOrder` | ✅ `PaysBridgeCreateOrderSignChainTest` | ✅ `PaysBridgeAlipayCoreSignChainTest` | ✅ `PaysBridgeDouyinCoreSignChainTest` | ✅ `PaysBridgeQqCoreSignChainTest` |
| 查单 `queryOrder` / 关单 `closeOrder` | ✅ `PaysBridgeQuerySignChainTest` | ✅ `PaysBridgeAlipayCoreSignChainTest` | ✅/❌（仅查单，关单不支持）`PaysBridgeDouyinCoreSignChainTest` | ✅/✅ `PaysBridgeQqCoreSignChainTest` |
| 退款 `refund` / 查退款 `queryRefund` | ✅ `PaysBridgeRefundSignChainTest` / `PaysBridgeQuerySignChainTest` | ✅ `PaysBridgeAlipayCoreSignChainTest` | ✅ `PaysBridgeDouyinCoreSignChainTest` | ✅ `PaysBridgeQqCoreSignChainTest` |
| 分账 `profitSharing` | ✅ `PaysBridgeAdvancedSignChainTest` | ✅ `PaysBridgeAlipayProfitSharingSignChainTest` | ✅ `PaysBridgeDouyinCoreSignChainTest` | ❌ |
| 转账 `transfer` | ✅ `PaysBridgeAdvancedSignChainTest` / `PaysBridgeTransferRedPacketSignChainTest` | ✅ `PaysBridgeAlipayTransferSignChainTest` | ❌ | ❌ |
| 红包 `red_packet` | ✅ `PaysBridgeAdvancedSignChainTest` / `PaysBridgeTransferRedPacketSignChainTest` | ✅ `PaysBridgeAlipayRedPacketSignChainTest` | ❌ | ❌ |
| 订阅 `subscription` | ✅ `PaysBridgeSubscriptionSignChainTest` | ✅ `PaysBridgeAlipaySubscriptionSignChainTest` + `…LifecycleSignChainTest` | ❌ | ❌ |
| 对账 `reconciliation` | ✅ `PaysBridgeReconciliationSignChainTest` | ✅ `PaysBridgeAlipaySettlementWithdrawReconcileSignChainTest` | ❌ | ❌ |
| 余额 `balance` | ❌ 不支持 | ✅ `PaysBridgeAlipayBalanceQueryTest` | ❌ | ❌ |
| 结算 `settlement` | ✅ `PaysBridgeSettlementSignChainTest` / `PaysBridgeBalanceSettlementQueryTest` | ✅ `PaysBridgeAlipaySettlementWithdrawReconcileSignChainTest` | ❌ | ❌ |
| 个人收款 `personal_receive` | ✅ `PaysBridgePersonalReceiveSignChainTest` | ⏳ 能力支持·e2e 待补 | ❌ | ❌ |
| Webhook 事件 `webhook` | ✅ `PaysBridgeWechatV3WebhookVerifyTest`（验签+解密双链） | ✅ `PaysBridgeWechatV3WebhookVerifyTest` | ✅ `PaysBridgeDouyinNotifyVerifyTest` | ✅ `PaysBridgeQqNotifyVerifyTest` |

### 微信 V3 专属链路

| 链路 | 状态 | 验证用例 |
|------|:----:|----------|
| 入站通知解密（AES-256-GCM） | ✅ | `PaysBridgeWechatV3NotifyDecryptTest` |
| 出站签名（批量转账 `batchTransfer` / `transferQuery` / `transferReceipt`） | ✅ | `PaysBridgeV3SignChainTest` |
| Webhook 验签（RSA-SHA256 + 平台证书信任链）+ 解密 | ✅ | `PaysBridgeWechatV3WebhookVerifyTest` |

### 明确「大声失败」契约（非静默）

| 能力 | 渠道 | 行为 | 验证用例 |
|------|------|------|----------|
| 日终余额 `balanceQueryDayEnd` | 全渠道 | 支付宝 `queryDayEndBalance` 为 `methodNotSupported` 桩，其余渠道经 `method_exists` 守卫直接抛 `RuntimeException`，**不静默成功** | `PaysBridgeAlipayBalanceDayEndCapabilityTest` |

## 4. 运行时能力发现 API

无需支付配置即可按渠道动态渲染能力菜单（前端一次性渲染能力开关）：

```php
use Kode\MiniApp\Union\Union;

// 单渠道能力画像（基础能力 + 高级支付 10 项布尔开关）
$profile = Union::wechat()->capabilityProfile();
// $profile['features'] 基础能力； $profile['payment'] 高级支付 10 项布尔

// 等价快捷入口（门面级，等价于 advancedPay()->paymentCapabilities()）
$caps = Union::wechat()->paymentCapabilities();
// => ['profit_sharing' => true, 'transfer' => true, ... 'balance' => false, 'webhook' => true]

if ($caps['red_packet']) {
    // 仅微信 V2 / 支付宝支持红包
}

// 单能力显式判断
if (Union::wechat()->supportsRefund()) {
    Union::wechat()->refund([/* ... */]);
}
```

> 所有 `supports*()` 与 `paymentCapabilities()` 均经 `method_exists` 守卫；调用未支持能力时适配器抛**清晰异常**（含方法名与渠道），绝不产生 `Call to undefined method` 类致命错误。

## 5. 跨渠道签名对照结论

核心下单生命周期（下单 / 查单 / 关单 / 退款 / 查退款）与高级能力（分账 / 转账 / 红包 / 订阅 / 结算 / 对账）在 **微信 V2（XML+MD5） × 支付宝（RSA2）双渠道均已 e2e 验证签名链**，闭合跨渠道对称性。V3 入站解密 / 出站签名 / Webhook 验签双链均已补齐。

**待补项（诚实标注，非伪造）**：

- 支付宝 `personal_receive`：能力支持，e2e 签名链待补（其余支付宝能力均已验证）。
