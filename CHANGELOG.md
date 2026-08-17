# Changelog

> 本文件随版本提交到仓库，作为对外发布记录（与 GitHub Releases / Packagist 同步）。

## v2.0.38（2026-08-17 发布）

### 非支付能力发现增强 + 文档 + CHANGELOG 收尾

- **能力发现增强**：新增 `ChannelFeature::Phone` 枚举项（label「手机号」）；修正 `Channel::features()` 声明漂移——飞书 Lark 实际支持解密 + 手机号（由 `PhoneByDecryptTest` 以 `Channel::Lark` 证明）却仅声明 `[Login,User]`，补 `Decrypt` + `Phone`；全渠道按实测补 `Phone` 能力（微信 / 企业微信 / 支付宝 / 抖音 / 百度 / QQ）。
- **回归守护**：新增 `tests/Union/ChannelFeatureMatrixTest`，以预期矩阵断言 `Channel::features()` 逐渠道一致，防止能力声明漂移（呼应支付侧 `PaysBridgeCapabilityMatrixConsistencyTest`）。
- **文档订正**：`docs/union.md` 退款能力基线表述由「以 applyRefund 为基线」改为「以 `refund()` 为基线」，与 v2.0.36 代码一致。
- **CHANGELOG**：补齐 v2.0.0–v2.0.37 全部发布记录（共 38 个版本）。

## v2.0.37（2026-08-17 发布）

- feat: 补齐支付宝 `personal_receive` 核心签名链 e2e（`createQrCode`/`queryRecords`/`withdraw`/`queryWithdraw` 四方法 RSA2 真实签名链），闭合个人收款 e2e 缺口；能力矩阵文档支付宝 `personal_receive` 由 ⏳ 改 ✅。

## v2.0.36（2026-08-17 发布）

- fix: 修正 `supportsRefund` 能力漂移（改探 `refund()` 替代 `applyRefund`，修复抖音/QQ 误报不支持退款）；新增抖音 MD5+salt / QQ V3 RSA-SHA256 核心签名链 e2e，闭合跨渠道核心下单生命周期对称性。

## v2.0.35（2026-08-17 发布）

- docs: 能力矩阵文档落地——新增 `docs/CAPABILITY_MATRIX.md`（高级支付能力矩阵 + 跨渠道真实网关签名链 e2e 验证总览 + 零漂移保证），README 补高级支付能力矩阵摘要与链接。

## v2.0.34（2026-08-17 发布）

- feat: 新增微信 V3 Webhook 验签+解密双链（`verifyWebhook` 补全历史「只解密不验签」安全缺口）。

## v2.0.33（2026-08-17 发布）

- feat: 补齐支付宝核心下单/查单/退款/查退款/关单真实网关 RSA2 签名链 e2e，与微信 V2 MD5 侧形成跨渠道对照。

## v2.0.32（2026-08-17 发布）

- fix: 补完微信 V3 入站通知解密（修复历史死代码分支）。

## v2.0.31（2026-08-17 发布）

- feat: 补齐支付宝结算/对账真实网关 RSA2 签名链 e2e。

## v2.0.30（2026-08-17 发布）

- feat: 补齐支付宝订阅生命周期真实网关 RSA2 签名链 e2e。

## v2.0.29（2026-08-17 发布）

- test: 能力矩阵一致性回归 + `balanceQueryDayEnd` 契约测试。

## v2.0.28（2026-08-17 发布）

- chore: 清理废弃类 + 支付宝高级能力真实网关签名链 e2e。

## v2.0.27（2026-08-16 发布）

- feat: 新增对账 `download-bill`/`fund-flow` V2 MD5 签名链 e2e。

## v2.0.26（2026-08-16 发布）

- feat: 新增跨渠道余额查询支持矩阵 e2e。

## v2.0.25（2026-08-16 发布）

- feat: 新增支付宝余额查询真实网关签名链 e2e。

## v2.0.24（2026-08-16 发布）

- feat: 新增结算查询 V3 签名链 e2e + 余额不支持守卫。

## v2.0.23（2026-08-16 发布）

- feat: 新增 V3 证书签名链 e2e（批量转账/查询/电子回单）。

## v2.0.22（2026-08-16 发布）

- docs: 新增错误码归一化契约测试 + 映射文档。

## v2.0.21（2026-08-16 发布）

- feat: 新增结算真实网关签名链 e2e。

## v2.0.20（2026-08-16 发布）

- feat: 新增转账 + 红包真实网关签名链 e2e。

## v2.0.19（2026-08-16 发布）

- feat: 新增个人收款真实网关签名链 e2e。

## v2.0.18（2026-08-16 发布）

- feat: 新增订阅生命周期 + 分账查询/退回/解冻签名链 e2e。

## v2.0.17（2026-08-16 发布）

- feat: 新增查单/关单/查退款签名链 e2e（`pay/orderquery`、`pay/closeorder`、`pay/refundquery`）。

## v2.0.16（2026-08-16 发布）

- feat: 新增抖音（MD5+salt）+ QQ（MD5+api_key）回调验签 e2e。

## v2.0.15（2026-08-16 发布）

- feat: 新增支付宝 RSA2 回调验签 + 微信订阅签名链 e2e。

## v2.0.14（2026-08-16 发布）

- feat: 升级 kode/pays 至 2.17.0 + 微信 Webhook 能力激活（真实 e2e）。

## v2.0.13（2026-08-16 发布）

- feat: 新增微信 V2 回调验签 e2e（资金确认路径）。

## v2.0.12（2026-08-16 发布）

- feat: 新增高级能力签名链 e2e（转账/分账/红包）。

## v2.0.11（2026-08-16 发布）

- test: 真实退款端到端「签名拼装全链路」闭环验证（与下单对称）。

## v2.0.10（2026-08-16 发布）

- feat: 统一异常归一化——kode/pays 网关 `PayException` 归一为 `ApiException`。

## v2.0.9（2026-08-15 发布）

- test: 真实下单端到端「签名拼装全链路」闭环验证。

## v2.0.8（2026-08-15 发布）

- feat: 新增 `capabilityProfile()` 统一能力画像（基础能力 + 高级支付子能力合并树）。

## v2.0.7（2026-08-15 发布）

- feat: 顶层 `paymentCapabilities()` 能力菜单发现 + 真实 Kernel resolver 端到端验证。

## v2.0.6（2026-08-15 发布）

- fix/feat: 发布脚本一键双推 + 支付能力门面冒烟测试。

## v2.0.5（2026-08-15 发布）

- fix/docs: 能力矩阵实测核对修正 + `paymentCapabilities` 汇总。

## v2.0.4（2026-08-15 发布）

- feat: 退款闭环 / 个人收款 / 加密货币能力增强。

## v2.0.3（2026-08-15 发布）

- feat: Webhook 事件回调能力（前向兼容 kode/pays 2.6.0）。

## v2.0.2（2026-08-15 发布）

- feat: 高级支付能力扩展——红包 / 订阅 / 余额 / 结算 + 能力发现补全。

## v2.0.1（2026-08-15 发布）

- feat: 高级支付能力发现 API（`supports*`）+ 渠道能力矩阵文档。

## v2.0.0（2026-08-15 发布）

- feat: 支付中枢 kode/pays 硬依赖化 + 高级支付能力（分账/转账/对账）。破坏性重构：移除全部内置支付，kode/pays 为唯一 composer `require` 级硬依赖；`Union::pay()/notify()` pays-only，缺失即抛 `RuntimeException`；高级能力经 `AdvancedPayAdapter` + `Union::advancedPay()` 暴露。

## v1.42.2（2026-08-14 发布）

- 微信支付 V3 统一（全端交易类型 + 服务商模式）、能力发现 + 配置契约、微信开放平台配置契约与错误传播加固。详见下方各节。

## v1.42.3（2026-08-14 发布）

- 登录与支付强绑定（openid 自动关联 + fail-fast）、微信支付统一 V3（全端交易类型 + 服务商模式）、微信开放平台配置契约与错误传播加固。详见下方各节。

## v1.42.4（2026-08-14 发布）

### 文档与契约表述订正

- **纠正「内置支付 = 历史保留 / 将移交」的过时表述**：`PayAdapter` 契约 docblock 与 `docs/union.md`「支付能力归属说明」重写为「本包基础支付与 kode/pays 是分工（身份 → 支付），不是替代」。明确：本包内置支付已生产级（V3 签名 / 服务商 / 全端 / openid 自动关联），可独立承载下单 + 回调；kode/pays 是可选的「支付中枢增强」（退款 / 对账 / 沙箱 / 多渠道聚合），且**不处理登录、拿不到 openid**——付款人标识始终来自本包登录流程。
- **修正被推翻的能力声明**：`docs/union.md` 能力发现章节原称「微信 H5 / PC 暂未实现支付」，已改为「H5 / PC 已支持支付（MWEB / NATIVE，无需 openid，故不声明 Decrypt）」；同步更新示例代码。
- **新增「支付前必须先登录」要点**（`docs/wechat.md`）：直接回应旧方案「支付时另外查库取 openid 再拼」的坑——openid 必须来自**当次**微信登录（code2session / OAuth）且与 appid 强绑定，库里没记录的新用户必须先走当次登录才能 JSAPI 支付（微信平台级硬规则，任何 SDK 无法绕过）；推荐支付入口即触发当次登录，用 `UnionUser` 注入 openid，而非业务自查历史库。
- **kode/pays 桥接示例更新**：体现 `user: $user` 自动注入 openid（V3 `amount.total` 结构），强调 openid 由本包登录提供。

## v1.42.5（待发布）

### 弃用与工具链：内置支付标记 `@deprecated` + phpstan 脚本消除 OOM

- **内置支付适配器标记 `@deprecated`**：各端基础支付适配器（`Wechat`/`Qq`/`Douyin`/`Alipay`/`Baidu`/`WeWork` 的 `*PayAdapter`）与底层 `Providers/*/Modules/Pay.php` 现标注 `@deprecated`，明确指向 `Union::payViaPays()` / `Union::notifyViaPays()`（即 kode/pays）。**无 BC 破坏、不丢渠道**：它们仍作为未安装 kode/pays 时的向后兼容 fallback 继续可用；`BasePayAdapter` 与 `PaysBridgePayAdapter` 不弃用（前者是共享基类、后者是推荐桥接）。此举为「支付终将完全移交 kode/pays」释放明确信号，待 kode/pays 覆盖全部渠道（含抖音/QQ/百度）后于某 major 版本移除。
- **phpstan 脚本消除 OOM 误报**：`composer.json` 的 `scripts.phpstan` 增加 `--memory-limit=1024M`，使 `composer phpstan` / `composer check` 在默认 `memory_limit=128M` 环境下全量分析不再 OOM（此前需手动带该参数）。

### 审计修复（发布前）：命名对齐 + 向后兼容 + 自动优先回归

- **对外支付方法名对齐 kode/pays（`createOrder`）**：`PayAdapter` 契约及所有实现（内置 6 适配器、`PaysBridgePayAdapter`、`PlatformUnion` 便捷方法）的下单方法由 `unifiedOrder` 统一更名为 `createOrder`，与 kode/pays 网关契约一致，使「装 pays 后调用代码零改动」。为保持 1.x 向后兼容（非 major 版本不破坏公开 API），保留 `unifiedOrder()` 作为 `@deprecated` 别名，内部直接转发到 `createOrder`，**将在 2.0 移除**。
- **修复自动优先 pays 的渠道回归**：此前 `Union::pay()` 在 kode/pays 安装后会对任意渠道自动切换到 pays 桥接，但默认 Kernel resolver 仅覆盖微信 / 支付宝，导致抖音 / QQ / 百度用户装了 pays 后下单反而报「resolver 未覆盖」。现新增 `PaysBridge::supportsKernel(Channel)` 守卫，`Union::pay()` 仅对覆盖渠道自动切换，其余渠道（抖音 / QQ / 百度）回退到仍可用的内置适配器；显式 `payViaPays()` 对未覆盖渠道仍大声失败并给出指引。
- **文档回归**：README 支付示例由旧名 `unifiedOrder` 统一改为 `createOrder`。
- 测试：新增 `PaysBridgePayerInjectionTest` 中「抖音装 pays 仍回退内置」「payViaPays 未覆盖渠道大声失败」「unifiedOrder 别名转发 createOrder」三例。

### 增强：微信支付绑定正确性加固

- **服务商模式 `sub_openid`**：微信支付 V3 在服务商（`sub_appid`）模式下，付款人属于特约商户 appid，要求 `payer.sub_openid`（非直连商户的 `payer.openid`）。`Wechat\Modules\Pay::buildPayload` 现按是否服务商自动归并——业务侧照常传 `openid`（或传已登录 `UnionUser`），服务商模式落到 `payer.sub_openid`，直连模式落到 `payer.openid`；显式 `payer.sub_openid` 保持原样。
- **渠道守卫**：`WechatPayAdapter::unifiedOrder()` 增加校验——传入的 `UnionUser` 若非微信生态渠道（误传支付宝 / 抖音等），立即抛 `InvalidArgumentException`，避免把其他平台 `user_id` 错当成微信 `openid` 注入而下单成功却付错人。
- 说明：支付宝 `alipay.trade.create`（小程序）与抖音 `create_order` 的客户端会话已绑定用户，服务端 create 无需付款人标识，故「自动注入付款人」模式仅微信 JSAPI 适用；此差异已在文档注明，避免误用。
- 测试：`PayModuleTest` 新增服务商 JSAPI→`payer.sub_openid`、显式 `sub_openid` 保持；`WechatPayAdapterTest` 新增误传非微信用户抛异常。
- 文档：`docs/wechat.md` 服务商章节补 `sub_openid` 说明、绑定章节补渠道守卫说明。

### 增强：登录与支付强绑定（openid 自动关联 + fail-fast）

- **契约升级**：`PayAdapter::unifiedOrder(array $order, ?UnionUser $user = null)` 新增可选 `UnionUser` 参数，明确「支付依赖本平台登录用户」这一平台硬约束；微信开放平台之外的各平台适配器（QQ / 抖音 / 企业微信 / 支付宝 / 百度 / kode/pays 桥接）同步接收该参数（按契约透传，为后续平台绑定用户标识预留）。
- **微信 JSAPI openid 自动关联**：`WechatPayAdapter::unifiedOrder()` 在 JSAPI（公众号 / 小程序）场景下，若传入已登录 `UnionUser` 则自动注入其 `openid`；同时兼容业务侧显式传 `openid`（顶层）或 `payer.openid` 两种写法。`Pay::buildPayload()` 新增交易类型感知：JSAPI 下把顶层 `openid` 提升为微信 V3 规范的 `payer.openid`。
- **fail-fast 校验**：JSAPI 下单若既无 `openid` 又无已登录用户，适配器立即抛 `InvalidArgumentException`（清晰提示需来自微信登录），避免微信侧含糊的「参数错误」。`APP` / `H5` / `NATIVE` 等不需要 openid 的交易类型不受约束。
- **便捷写法**：`Union::wechat()->unifiedOrder($order, user: $user)` 经 `PlatformUnion` 透传用户到支付适配器，一行完成「登录 → 支付」。
- 文档：`docs/wechat.md` 支付章新增「登录与支付强绑定（openid 自动关联）」专节（含自动注入 / 显式携带 / fail-fast 说明）。
- 测试：新增 `WechatPayAdapterTest` 用例（自动注入 openid 到 payer.openid、缺 openid 抛异常、PlatformUnion 透传用户），`PayModuleTest` 新增 JSAPI openid→payer 提升用例。

### 增强：微信支付统一 V3（全端交易类型 + 服务商模式）

- **全端交易类型分派**：`Wechat\Modules\Pay::order(string $tradeType, array $params)` 支持 `JSAPI`（公众号 / 小程序）、`APP`（移动应用）、`H5`（MWEB）、`NATIVE`（PC 扫码）四种交易类型，分别打对应 V3 端点；非法交易类型抛 `InvalidArgumentException`。此前仅有 JSAPI 且 App 走割裂的 V2 第三方平台路径。
- **服务商模式（开放平台关联）**：`WechatConfig` 新增 `spMchId()` / `subMchId()` / `subAppId()` / `isServiceProvider()`；下单 body 自动切换为 `sp_mchid` / `sub_mchid` / `sub_appid`，V3 签名头 `mchid` 取 `sp_mchid`，`query` / `close` 的商户参数同步切换。即一个服务商可代关联的公众号 / 服务号 / App / H5 等特约商户收款。
- **统一适配器分派**：`WechatPayAdapter` 作为基类（JSAPI），新增 `WechatAppPayAdapter`(APP) / `WechatH5PayAdapter`(MWEB) / `WechatPcPayAdapter`(NATIVE) 子类；删除旧的 `WechatOpen\AppPayAdapter`（V2 `/pay/unifiedorder` 旧路径），微信全端统一在同一套 V3 签名体系下。`Union::pay()` 注册表将 WechatMini/Mp/App/H5/Pc 分别映射到对应适配器，`Channel::features()` 为 H5 / PC 补 `Pay` 能力。
- **配置契约同步**：`WechatConfig::requiredKeysFor(Pay)` 按是否服务商返回不同必填键（直连 `mch_id` / 服务商 `sp_mchid`+`sub_mchid`，均含 `key_path`+`mch_serial_no`）。
- 文档：`docs/wechat.md` 支付章重写为「全端 V3 + 服务商模式」含各交易类型示例；`docs/union.md` 支付能力归属说明更新为内置即覆盖多端+服务商；README 能力矩阵微信支付行更新。
- 测试：改造 `tests/Providers/Wechat/PayModuleTest.php`（新增 APP/H5/NATIVE 端点 + 服务商 + 非法类型用例）、`tests/Union/WechatAppPayAdapterTest.php`（改写为 V3 App 适配器）、新增 `tests/Union/WechatPayAdaptersTest.php`（5 端经 Union 分派到正确 V3 端点）；同步更新 `ChannelFeatureTest` / `CapabilityDiscoveryTest` 中 H5/PC 已支持支付的断言。PHPStan level 8 / phpcs PSR12 / PHPUnit 全绿（484 tests / 1361 assertions）。

### 增强：微信开放平台配置契约 + 错误传播加固

- **配置契约补齐**：`WechatOpenConfig` 此前未覆写配置契约（与上一轮「能力发现 + 配置契约」特性脱节），导致 `Union::capabilities(Channel::WechatOpen)` 报告不出必填配置。本次新增 `requiredKeys()`（平台级必填 `component_appid` / `component_secret` / `token` / `encoding_aes_key`）、`requiredKeysFor(ChannelFeature)`（Login/User 同平台级、Notify 仅 `token`+`encoding_aes_key`、Pay 不支持返回空）、`validate()` / `validateFeature()`。校验按「生效值」判断并兼容 `app_id` / `secret` / `aes_key` 等别名，避免用别名配置被误判缺失。
- **错误传播加固（消灭静默失败）**：`Component` 的 `accessToken` / `preAuthCode` / `queryAuth` / `refreshAuthorizerToken` / `authorizerInfo` / `authorizerOption` / `setAuthorizerOption` / `authorizerList`，以及 `Authorizer::miniProgramSession`，原先以 `json_decode` 裸解析、微信返回 `errcode` 时静默返回错误体 / 空数组，业务侧极易把失败当成功。全部改为经 `ApiResponse::fromPsr(...)->throwIfFailed(...)` 统一抛出 `ApiException`（携带 `errcode` / `errmsg` / 平台 / 动作）；`accessToken` 同时改用 `ApiResponse` 解析并返回真实 `expires_in`。`ComponentLoginAdapter` 中冗余的 errcode 手动检查随之移除（已由 `queryAuth` 的抛出语义覆盖）。
- 文档：`docs/wechat-open.md` 新增「配置契约与能力校验」「API 错误传播」两节（必填项表 + 别名兼容说明 + `ApiException` 捕获示例），并补入目录。
- 测试：新增 `tests/Providers/WechatOpen/WechatOpenConfigContractTest.php`（requiredKeys / requiredKeysFor / 别名兼容 / 缺键抛 `ConfigException` / `Union::capabilities` 聚合）、`tests/Providers/WechatOpen/WechatOpenModuleErrorTest.php`（9 例覆盖上述各方法 errcode 抛 `ApiException` + 正常响应不误伤）。

### 增强：渠道能力发现 + 配置契约（开发者友好）

### 增强：渠道能力发现 + 配置契约（开发者友好）

- 新增 `Contracts/ChannelFeature` 枚举（Login / Pay / Notify / User / Decrypt），刻画各渠道「支持哪些能力」。
- `Union` 新增 `capabilities(Channel)`：一次性返回某渠道的能力集合与启用这些能力所需的必填配置键（去重），支持运行前自检；`Channel` 枚举同时新增 `features()` / `supports()` / `providerKey()`。能力映射**如实反映当前适配器覆盖**（如微信 H5 / PC 暂未实现支付 → `Pay=false`；微信开放平台无独立支付适配器）。
- `ConfigInterface` / `BaseConfig` 新增配置契约：`requiredKeys()`（平台级必填）、`requiredKeysFor(ChannelFeature)`（能力级额外必填）、`validate()` / `validateFeature()`（缺键抛 `ConfigException` 并列出缺失项，fail-fast 不再跑到深处才暴露）。
- `WechatConfig` 覆写：`requiredKeys()=>['app_id']`，`requiredKeysFor(Pay)=>['mch_id','key_path','mch_serial_no']`；`AlipayConfig` 覆写：`requiredKeys()=>['app_id','private_key','public_key']`。其余平台继承默认空，框架已就绪可逐步补充。
- 文档：`docs/union.md` 新增「能力发现与配置契约」专章；`docs/wechat.md` 与 `docs/union.md` 支付章标注内置支付的能力范围（彼时仅为直连商户 JSAPI；后续已在 v1.42.2 统一补全全端交易类型与服务商模式，见下）。
- 测试：新增 `tests/Union/ChannelFeatureTest.php`、`tests/Providers/Wechat/WechatConfigContractTest.php`、`tests/Union/CapabilityDiscoveryTest.php`（共 17 例）。PHPStan level 8 / phpcs PSR12 / PHPUnit 全绿（475 tests / 1331 assertions）。

### 修复：微信支付 V3 请求缺失 Authorization 签名头（生产必 401）

- 重大缺陷修复：`Wechat\Modules\Pay`（V3 接口 `/v3/...`）此前直接 `postJson/get` 到 `api.mch.weixin.qq.com`，**从未附加 `Authorization` 头**，微信商户平台对每个 V3 请求强制校验 `WECHATPAY2-SHA256-RSA2048` 签名，缺失即 401。此前微信支付矩阵「✅」名不副实。
- 新增 `Providers/Wechat/V3Signer`：按规范对 `method\nurl(path+query)\ntimestamp\nnoncestr\nbody\n` 以商户私钥做 SHA256withRSA 签名并 Base64 编码，生成 `Authorization: WECHATPAY2-SHA256-RSA2048 mchid="",nonce_str="",signature="",timestamp="",serial_no=""`。
- `WechatConfig` 新增 `mchSerialNo()`（`mch_serial_no`）配置；`Pay` 模块每次请求自动注入签名头（POST 对请求体签名、GET 对空体签名），并对 `key_path` / `mch_serial_no` 缺失给出明确错误。
- 文档：README / `docs/wechat.md` 配置示例补充 `mch_serial_no` 与 `key_path` 必填说明；`docs/union.md` 桥接字段映射补 `mch_serial_no`。
- 测试：新增 `tests/Providers/Wechat/V3SignerTest.php`（4 例：头格式 / 公钥验签 / GET 空体 / 每次签名不同）、`tests/Providers/Wechat/PayModuleTest.php`（9 例：7 个 V3 接口均带可验签 Authorization 头 + 2 个缺失配置抛错）；并修复既有 `WechatPayAdapterTest` 因签名要求缺失而失败。抽出复用桩 `tests/Fakes/CapturingHttpClient.php`。

## v1.42.1（2026-08-14 已发布）

### 测试：补齐 5 个 Union 回调适配器（Notify）回归覆盖

- 微信 / 支付宝 / 百度 / 抖音 / 企业微信 5 个回调适配器此前零测试（QQ 已在 v1.42.0 覆盖）。回调（支付确认、消息推送）属生产关键路径，归一化行为此前无锁定。
- 新增 `tests/Union/NotifyAdaptersTest.php`（5 例，31 assertions）：逐平台验证 `Union::<platform>()->notify()` 返回 `NotifyAdapter` 且 `channel()` 正确，`decode()` 字段映射锁定（微信 total_fee 转 int、支付宝 total_amount/trade_status、百度 status、抖音 result_code、企业微信 event_type）。
- 复用 `tests/Fakes/FakeHttpClient` 桩，遵循项目测试约定（不内联 HttpClientInterface）。

### 文档：补齐 Union 级支付回调（Notify）用法章节

- `README.md` 在「微信/支付宝支付回调通知」后新增「统一支付回调（Union 入口）」：展示 `Union::<platform>()->notify()->decode()` 跨端归一化用法，并明确「`decode()` 仅归一化、不验签；验签须走各 Provider 自带 `notify()` 或 QQ 内置 `Qq\Modules\Notify`」。
- `docs/union.md` 新增「支付回调（Notify）统一入口」整章 + 「回调渠道支持矩阵」（8 渠道 × Union 归一化 / Provider 验签 / 说明），与能力支持矩阵呼应。

### 测试：补齐 QQ 支付衍生方法（orderQuery / closeOrder / refund）Provider 级覆盖

- `Providers/Qq/Modules/Pay` 三方法此前零测试（仅 `unifiedOrder` 经 Union 适配器测试过），真实实现但未被锁定。
- 新增 `tests/Providers/Qq/PayModuleTest.php`（3 例，15 assertions）：逐方法验证命中正确端点（orderquery/closeorder/refund）、请求体携带业务参数、XML 响应正确解析，并**以请求体重算 `Pay::sign` 锁定签名一致性**（下单/查询/关单/退款/回调验签共用同一签名算法，杜绝漂移）。
- 沿用 `QqPayAdapterTest` 内联 XML 客户端桩模式（canonical `FakeHttpClient`/`FakeResponse` 仅支持 JSON body，不适用 QQ 支付 XML 端点）。

## v1.42.0（2026-08-13 已发布）

### 增强：补齐 QQ 支付回调（Notify）能力

- 真实能力缺口修复：QQ 小程序支付已有下单（`QqPayAdapter`）但此前 `Union::qq()->notify()` 抛「不支持回调」（`Providers/Qq` 底层缺 Notify 模块）。
- 新增 `Providers/Qq/Modules/Notify`：QQ 支付回调为 XML 格式、MD5 签名（密钥 `api_key`），与下单算法一致；`verify()` 复用抽取后的 `Pay::sign()` 静态方法（保证验签与下单签名逐字节一致），`decode()` 解析 XML 并在签名失败时抛 `ApiException`。
- `Qq\Modules\Pay::sign()` 由私有方法重构为 `public static`，供下单与验签共用（消除会漂移的重复实现）。
- `QqApp` 新增 `notify(): Notify` 访问器；新增 `Union\Channels\Qq\QqNotifyAdapter`（回调数据归一化，与微信回调适配器结构一致）；`Union::buildNotifyAdapter` 为 `Channel::Qq` 接线。
- 现 9 个平台中 6 个具备支付回调能力（微信全场景 / 企业微信 / 支付宝 / 抖音小程序 / 百度小程序 / **QQ 小程序**），钉钉、飞书、微信开放平台无消费者支付场景（设计预期）。
- 测试：新增 `tests/Providers/Qq/NotifyTest.php`（4 例：合法签名验签通过 / 错误签名抛错 / 未配置 api_key 跳过验签 / 空 sign 返回 false）、`tests/Union/QqNotifyAdapterTest.php`（2 例：适配器可解析 + decode 归一化）。

### 工程：补齐 `#[Override]` 全包覆盖 + 质量门清理

- 用 php-parser + `ReflectionMethod::getPrototype()` 语义判定（与 PHP `#[Override]` 一致：覆盖重写父类方法 + 实现接口方法），向 60 个 `src` 文件补 `#[Override]`（218 处），消除本包唯一违反的项目规范（PHP 8.3+ 须用 `#[Override]`）。
- 顺带清理 6 个基线质量门错误（`QqPayAdapter` 死守卫、`tests/Fakes/FakeResponse` 类型注解、`KernelInterface::app()` 声明、`Kernel::wechatOpen()` 返回类型收窄等）。
- 质量门全绿：PHPStan level 8（1024M）0 错、phpcs PSR12 0 错、PHPUnit 424 tests / 1159 assertions。

### 文档：能力支持矩阵 + 统一敏感数据章节

- `README.md` 新增「能力支持矩阵」（登录 / 用户资料 / 解密 / 手机号 / 支付 / 回调 各渠道覆盖）与「统一敏感数据」章节（手机号 / 用户资料 / 加密数据的三族统一入口与典型用法）——此前 v1.22~v1.38 落地的统一解密能力在 README 完全缺失。
- `docs/union.md` 新增「能力支持矩阵」整章（Union 统一入口覆盖总览）。
- 补充 QQ 回调为 XML+MD5 验签的说明。

### 修复：百度 / 抖音 Union 支付适配器方法名错误（运行时静默失败）

- **真实 bug 修复**：`BaiduPayAdapter` 与 `DouyinPayAdapter` 此前调用 `$pay->createOrder($order)`，但底层 `Baidu\Modules\Pay` / `Douyin\Modules\Pay` 的下单方法名为 `create()`（与 Alipay/QQ/Wechat 适配器一致），导致 `Union::baidu()->pay()->unifiedOrder()` / `Union::douyin()->pay()->unifiedOrder()` 在运行时抛「未提供 createOrder 方法」——能力矩阵虽标注 ✅，实际完全不可用。
- 移除两适配器内 `method_exists($pay, 'createOrder')` 的误导性守卫，改为直接调用 `$pay->create($order)`，与 `AlipayPayAdapter`（`create`）/ `QqPayAdapter`（`unifiedOrder`）/ `WechatPayAdapter`（`order`）保持一致。
- 测试：新增 `tests/Union/BaiduPayAdapterTest.php`、`tests/Union/DouyinPayAdapterTest.php`（各 2 例：适配器可解析 + unifiedOrder 真实分派到底层 `create()` 返回预下单响应），锁定该回归。

### 文档：修正能力矩阵中企业微信「支付」过度声明

- 能力矩阵（README + docs/union.md）企业微信行「支付」单元格曾标注 ✅，但 `WeWorkPayAdapter::unifiedOrder()` 抛「暂未实现」（`WechatWork` Provider 无 Pay 模块，且支付能力按架构约定移交 `kode/pays`）。修正为「—（经 kode/pays）」以保证文档诚实、消除「✅ 却运行时失败」的误导。
- `WeWorkPayAdapter` 异常说明同步指向 `kode/pays` / `wechat` 主 Provider。

### 测试：补齐微信 App 支付适配器（唯一缺覆盖的支付适配器）回归测试

- 全量审计确认 9 平台的 Union 适配器方法调用均与底层 Provider 模块真实对齐（`openApp()`/`authorizer()`/`component()`/`auth()->user()`/`auth()->userDetail()` 等逐一核对一致），仅微信 App 支付（`WechatOpen\AppPayAdapter`，`Union::pay(Channel::WechatApp)`）此前无测试覆盖。
- 新增 `tests/Union/WechatAppPayAdapterTest.php`（4 例：适配器可解析且 channel=WechatApp / unifiedOrder 校验 authorizer 凭据缺失抛 `InvalidArgumentException` / unifiedOrder 真实分派到 `Authorizer::call('/pay/unifiedorder')` 返回预下单响应 / 静态门面可用），消除支付关键路径的测试盲区。
- 复用 canonical `tests/Fakes/FakeHttpClient` 桩，遵循项目测试约定（不内联 HttpClientInterface）。

### 测试：补齐支付宝 / 微信支付适配器（最后两块支付盲区）回归测试

- 全 6 个支持支付的渠道（微信小程序·公众号·App / 支付宝 / 抖音 / 百度 / QQ）Union 支付适配器现均有直接回归测试。
- 新增 `tests/Union/AlipayPayAdapterTest.php`（2 例：适配器可解析 + unifiedOrder 分派到 `Alipay\Modules\Pay::create()` 返回 `alipay_trade_create_response` 节点 `trade_no`）；网关 RSA2 签名所需私钥在 setUp 内 `openssl_pkey_new` 临时生成，`\assert($res !== false)` 收窄类型。
- 新增 `tests/Union/WechatPayAdapterTest.php`（3 例：适配器可解析 + unifiedOrder 分派到 `Wechat\Modules\Pay::order()`（V3 JSAPI 下单）返回 `prepay_id` + 公众号渠道复用同一适配器）。

> 注：v1.35.0 ~ v1.41.0（含统一解密 data/phone/userInfo 三族、值对象收口、kode/pays 桥接、支付软移交等）的逐版本变更记录见本地工作日志 `.workbuddy/memory/2026-08-09.md`；本 CHANGELOG 此前仅维护至 v1.34.0。

## v1.34.0

### 增强：各端 userInfo 资料字段标准化

- 新增 `Core\UserInfoNormalizer::normalize($raw)`：把各端 encryptedData 解密出的用户资料
  （兼容微信 getUserInfo 的 `nickName` / `avatarUrl` / `gender` / `city` / `province` /
  `country` / `language`）归一化为稳定的 snake_case canonical 键
  （`nickname` / `avatar` / `gender` / `city` / `province` / `country` / `language`），
  与登录 / profile 链路的 `UnionUser` 字段命名对齐。纯函数、绝不抛异常；缺失字符串字段填空串、
  `gender` 缺失为 null、值仅透传不做枚举映射（避免臆测各端编码）。
- `Union::userInfoByDecrypt()` / `userInfoByUser()` 现返回「原始字段保留 + 归一化 canonical 键」
  （与手机号路径一致）；新增静态便捷方法 `Union::normalizeUserInfo($raw)`（与 `Union::normalizePhone` 对称）。
- 新增 `tests/Core/UserInfoNormalizerTest.php`（4 例）并扩展 `tests/Union/UserInfoByDecryptTest.php`
  断言归一化键。

## v1.33.0

### 增强：把支付宝手机号并入统一入口（打破设计 fence）

- `Union` 新增 `phoneByResponse($channel, $response, ?$sign)`：把支付宝（小程序 / 生活号 / APP）
  `response` + `sign` 换取手机号也纳入统一手机号家族，与 `phoneByCode()`（微信 / 抖音）、
  `phoneByDecrypt()` / `phoneByUser()`（QQ / 百度 / 飞书 / 企业微信）并列。此前支付宝只能走
  `Union::alipay()->decrypt()->phone()` 底层入口，现有了与微信 / 抖音同级的统一入口。
- 传入 `$sign` 时先用 `config.public_key` 做 RSA2 验签（防中间人篡改），不传则跳过验签；
  返回结构经归一化为 `phoneNumber` / `purePhoneNumber` / `countryCode`（保留原始 `mobile`）。
- 非支付宝渠道调用抛 `InvalidArgumentException`。
- `docs/union.md` 手机号章节新增「统一支付宝手机号入口（response + sign）」小节，并把覆盖表
  中支付宝标记为「已支持（独立范式）」。

### 测试

- 新增 `tests/Union/PhoneByResponseTest.php`（5 例：支付宝 mini / mp / app 真实 AES 解密、
  合法 sign 验签通过、错误 sign 抛 ApiException、非支付宝渠道抛 InvalidArgumentException）。

## v1.32.0

### 增强：统一「encryptedData 解密获取用户资料」入口

- `Union` 新增 `userInfoByDecrypt($channel, $encryptedData, $sessionKey, $iv)`
  （显式 `session_key`）与 `userInfoByUser($channel, $encryptedData, $iv, $openId)`
  （自动取用登录托管的 `session_key`），与已有的 `phoneByDecrypt()` / `phoneByUser()` /
  `decrypt()` / `decryptByUser()` 对称，补齐「统一敏感数据解密」三族（data / phone / userInfo）。
- 覆盖微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 六个端（支付宝走 `Union::alipay()->decrypt()->data()`，
  因其为 `response` + `sign` 而非 `encryptedData`，不在范围内）；对不支持的渠道抛 `InvalidArgumentException`。
- 将原 `phoneDecryptChannel()` 分派辅助方法重命名为 `decryptChannel()`（data / phone / userInfo 共用），
  其默认错误消息中文化为「暂不支持 encryptedData 解密（手机号 / 用户资料）」。
- 测试：新增 `tests/Union/UserInfoByDecryptTest.php`（6 例）；同步更新 `PhoneByDecryptTest` 断言为新的错误消息。
- 文档：`docs/union.md` 手机号章节后新增「统一加密用户资料入口」小节。

## v1.31.0

### 增强：统一「encryptedData 解密获取手机号」入口

- `Union` 新增 `phoneByDecrypt($channel, $encryptedData, $sessionKey, $iv)`
  （显式 `session_key`）与 `phoneByUser($channel, $encryptedData, $iv, $openId)`
  （自动取用登录托管的 `session_key`），与已有的 `phoneByCode()`（code 换手机号）对称，
  共同构成完整的统一手机号获取 API 家族。
- 覆盖微信 / 抖音 / QQ / 百度 / 飞书 / 企业微信 六个端（支付宝走 `Union::alipay()->decrypt()->phone()`，
  因其为 `response` + `sign` 而非 `encryptedData`，不在范围内）；对不支持的渠道抛 `InvalidArgumentException`。
- 返回结构经 `Core\PhoneNormalizer` 归一化为 `phoneNumber` / `purePhoneNumber` / `countryCode`（原字段全部保留）。
- 飞书小程序的手机号即走此路径（`tt.getPhoneNumber` 返回 hex 编码 `session_key` 的 `encryptedData`），
  无需也不支持 code 换手机号。
- 新增 `tests/Union/PhoneByDecryptTest.php`，更新 `docs/union.md` 手机号章节。

## v1.30.0

### 修复

- **企业微信 decrypt watermark 校验 bug**：官方明确客户端敏感数据解密后 `watermark.appid`
  为**小程序 appId**，**并非**企业 corpid。原 `WechatWorkApp::decrypt()` 误用 `corpId()`
  校验，会导致合法数据被误判为伪造、或伪造数据被放行。`WechatWorkConfig` 新增 `appId()`
  （配置键 `app_id`），解密改以 `appId()` 校验 `watermark.appid`；未配置 `app_id` 时明确抛错。
- 同步修正文档（union.md / wechat-work.md）中关于「corpid 作为 watermark.appid」的错误描述。

### 增强：手机号输出归一化

- 新增 `Core\PhoneNormalizer`：将各端手机号原始数组（微信 `phoneNumber` / 支付宝 `mobile` 等）
  归一化为统一三元组 `phoneNumber` / `purePhoneNumber` / `countryCode`，缺失字段以空字符串填充，
  绝不抛异常。
- `Union::phoneByCode()` 返回经归一化兜底（原字段全部保留）。
- 支付宝 `Union::alipay()->decrypt()->phone()` 在保留 `mobile` 的同时追加统一三元组。
- 新增静态便捷方法 `Union::normalizePhone($raw)`，供业务侧对任意原始数组主动归一化。

### 测试

- 新增 `tests/Core/PhoneNormalizerTest.php`（5 例）；扩展 `WechatWork/DecryptTest.php`
  （corpid 不再被接受、缺 app_id 配置抛错）、`Alipay/DecryptTest.php`（归一化键）、
  `Union/PhoneByCodeTest.php`（跨端归一化一致）、`Union/DecryptTest.php`（企业微信 app_id 配置）。
  全量 353 tests / 927 assertions。

## v1.29.0

### 抖音 code 换手机号（RSA 密文变体）

- 新增共享解密工具 `Core\Crypto\RsaPkcs1`：base64 密文 + PKCS#1 v1.5 私钥分段解密，
  与对称族 `Aes128CbcPkcs7` 并列；私钥支持 PEM / 纯 Base64 / PKCS#1 / PKCS#8。
- 新增 `DouyinApp::phone()`：`byCode()` / `numberByCode()` / `pureNumberByCode()`，
  调 `api/apps/v1/get_phonenumber_info/`，用应用私钥解密并校验 `watermark.appid`；
  另有 `clientToken()` / `refreshClientToken()` / `forgetClientToken()`。
- `DouyinConfig` 新增 `appPrivateKey()`（配置项 `app_private_key`）。
- `Union::phoneByCode()` 分派新增 `Channel::DouyinMini`；match 扩为三元组，
  抖音接口不接受 `openid`，传入会被忽略。

### 修复

- `ApiResponse::errorCode()` 未识别抖音开放平台挂在 `data.error_code` 的错误码，
  会把失败响应判定为成功（静默失败）；同时补充 `err_msg` 错误信息字段。

### 测试

- 新增 `tests/Core/Crypto/RsaPkcs1Test.php`（10 例）、`tests/Providers/Douyin/PhoneTest.php`（15 例），
  均现场生成 RSA 密钥对造真实密文做 round-trip；`tests/Union/PhoneByCodeTest.php` 增抖音分派，
  `tests/Core/ApiResponseTest.php` 增嵌套错误码 3 例。全量 345 tests / 905 assertions。

> 注：OpenSSL 3.x 对 PKCS#1 v1.5 启用隐式拒绝（Marvin 攻击缓解），私钥不匹配时可能
> 不报解密错误而返回随机明文，最终表现为 JSON 解析失败——相关测试只断言异常类型。

## v1.28.0

### 新增：微信手机号快速验证（新版 code 换手机号）

- 新增 `Wechat\Modules\Phone`：调 `POST /wxa/business/getuserphonenumber?access_token=`，
  `byCode($code, ?$openId)` 返回完整 `phone_info`，`numberByCode()` / `pureNumberByCode()` 便捷方法。
  空 code 请求前拦截；`errcode` 非 0、缺 `phone_info`、字段不完整均抛 `ApiException`。
- `WechatApp` 新增 `phone(): Phone` 访问器。
- `Union` 新增统一入口 `phoneByCode(Channel, string $code, ?string $openId = null): array`，
  match 分派 `Channel::WechatMini`；其余渠道抛 `InvalidArgumentException`。
- 与旧版 `encryptedData` + `session_key` 解密（`Union::decrypt()`）互为并行路径，二者均保留。

### 覆盖范围

- 微信小程序 ✅；抖音有同类接口但返回 RSA 密文（需应用私钥，另一套凭证体系）暂不纳入；
  百度 / QQ 无此范式（仅 encryptedData）；支付宝走 response + sign。

### 测试

- `tests/Providers/Wechat/PhoneTest.php`（10 例）、`tests/Union/PhoneByCodeTest.php`（4 例）。
- 全量 316 tests / 790 assertions 全绿；PHPStan level 8 无错；phpcs PSR12 262/262 干净。

## v1.27.0

### 统一客户端敏感数据解密：企业微信小程序（corpid 作为 watermark.appid）

- **纳入企业微信小程序**：与微信同属 AES-128-CBC + PKCS#7 + session_key，仅明文 `watermark.appid` 实为**企业 corpid**。`WechatWorkApp::decrypt()` 以 `config->corpId()` 校验 watermark（其余 `data()/userInfo()/phone()` + `dataByUser()/userInfoByUser()/phoneByUser()` 语义对齐微信）。
- **新增 `WechatWork\Auth::session($code)`**：调 `miniprogram/jscode2session`（需先取 `access_token`），返回 `session_key`/`openid`/`userid` 并自动 `SessionKeyManager::for($config)->store()`；与「企业内部应用」的 `Auth::user($code)`（code→userid）是两套独立流程，互不干扰。
- **统一入口分派**：`Union::decrypt()` / `Union::decryptByUser()` 的 `match` 新增 `Channel::WechatWork => ['wechat_work', WechatWorkApp::class]`（provider key 为 `wechat_work`，映射到 `wechatWork()` 方法）。
- **测试**：新增 `tests/Providers/WechatWork/DecryptTest.php`（11 用例，corpId watermark）、`tests/Providers/WechatWork/AuthSessionKeyStoreTest.php`（stub 按 URL 路由 `gettoken`+`jscode2session`，断言自动托管与缺失不写入）；`tests/Union/DecryptTest.php` 增 `testUnionDecryptWechatWork` 且 `decryptByUser` 扩至企业微信。全量 **302 tests / 766 assertions** 全绿；PHPStan level 8 无错；phpcs PSR12 零错零 warning。
- **文档**：`docs/union.md` 渠道支持表 / 解密说明 / 端到端测试说明增企业微信（watermark=corpid 注解）。

## v1.26.0

### 统一客户端敏感数据解密：飞书小程序（hex 变体）

- **泛化共享解密工具支持 hex 编码变体**：`Core\Crypto\Aes128CbcPkcs7` 新增 `$encoding` 参数（`base64` 默认 / `hex`），飞书 `session_key` / `iv` 为 hex 编码、密文仍 base64、明文无 watermark，与微信系 base64+watermark 同属 AES-128-CBC 族。复用同一工具保持「统一方法」架构一致，避免新增分支。
- **新增飞书客户端解密**：`LarkApp::decrypt()` 复用 `Aes128CbcPkcs7(..., 'hex')`，`verifyAppId` 默认 `false`（跳过 watermark），暴露 `data()/userInfo()/phone()` + `dataByUser()/userInfoByUser()/phoneByUser()`，与微信系语义对齐。
- **飞书小程序登录 + session_key 托管**：`Lark\Auth` 新增 `appToken()/refreshAppToken()/forgetAppToken()`（`auth/v3/app_access_token/internal`）与 `session(string $code)`（`/open-apis/mina/v2/tokenLoginValidate`，Bearer `app_access_token`），登录成功后自动 `SessionKeyManager::for($config)->store($openId, $sessionKey)`。
- **统一入口分派**：`Union::decrypt()` / `Union::decryptByUser()` 的 `match` 新增 `Channel::Lark => ['lark', LarkApp::class]`，飞书纳入一站式解密。
- **测试**：新增 `tests/Providers/Lark/DecryptTest.php`（8 用例，hex 变体）+ `tests/Providers/Lark/AuthSessionKeyStoreTest.php`；`tests/Union/DecryptTest.php` 增 `testUnionDecryptLark` 与 `decryptByUser` 飞书覆盖。全量 **289 tests / 742 assertions** 全绿；PHPStan level 8 无错；phpcs PSR12 零错。
- **文档**：`docs/union.md` 渠道支持表 / 解密说明 / 端到端测试说明增飞书，并标注钉钉因无小程序 `session_key` 解密范式不在覆盖范围内。

## v1.25.0

### 统一客户端敏感数据解密：百度小程序

- 复用 `Aes128CbcPkcs7`，与微信 / 抖音 / QQ 同算法（base64 + watermark），`BaiduApp::decrypt()` 暴露 `data()/userInfo()/phone()` + ByUser 一站式。
- `Union::decrypt()` / `Union::decryptByUser()` 分派新增百度；`Baidu\Auth::session()` 登录后自动托管 `session_key`。
- 新增 `tests/Providers/Baidu/DecryptTest.php`（10 用例）+ `AuthSessionKeyStoreTest.php`；全量 PHPUnit 278/724 全绿。

## v1.21.0

### 健壮架构：微信资料失败语义统一 + 渠道分派修复

- **修复微信资料真实错误被静默吞掉**：`WechatUserAdapter` 的公众号 `cgi-bin/user/info` 与开放平台 `sns/userinfo` 拉取路径此前把**任何** `errcode != 0`（含 `40001` 令牌失效、`40003` openid 非法、`50001` 未授权）都吞进占位 `UnionUser`，与其他平台（支付宝/抖音/QQ 等一律抛 `ApiException`）行为不一致。新增 `WechatProfileError` 收敛「预期内错误 vs 真实错误」判定：`48001`（用户未关注/未授权 userinfo）降级为空资料、其余真实错误统一抛 `ApiException`，与全平台失败语义对齐。
- **修复 `Union::profile` 渠道分派丢失**：`Union::profile(Channel $channel, ...)` 此前仅用 `$channel` 选择适配器，但微信/抖音/支付宝适配器内部以 `payload['channel']` 判定子渠道、缺省回退默认渠道，导致 `Union::profile(Channel::WechatApp, ...)` 被误当公众号 mp 处理（App/PC 资料拉取静默失效）。`Union::profile` 现把调用方渠道带入 payload，确保子渠道正确。
- **补齐全平台 profile 端到端测试**：`tests/Union/UserProfileTest.php` 新增微信 mp 与开放平台 App 的资料拉取用例（成功归一化 + `48001` 良性空资料 + `40001` 真实错误抛错）。

## v1.20.0

### 真实对接增强：飞书资料嵌套字段归一化 + 全平台 profile 测试补齐

- **修复飞书资料昵称 / 头像静默丢失**：飞书 `contact/v3/users` 返回的 `name`（`{zh_cn, en_us}`）与 `avatar`（`{avatar_origin, avatar_240, avatar_72}`）为嵌套对象，而 `UnionUser::fromRaw` 只识别扁平字符串字段，导致飞书用户的 `nickname` / `avatar` 一直为空。`LarkUserAdapter` 新增 `normalize()` 将其归一成 `nick_name` / `avatar_url`（优先 `zh_cn` / `avatar_origin`）。
- **补齐全平台 profile 端到端测试**：`tests/Union/UserProfileTest.php` 新增支付宝（`alipay.user.info.share`）、钉钉（`topapi/v2/user/get`）、飞书（`contact/v3/users`）资料拉取用例（成功归一化 + 错误真实抛错 + 飞书嵌套字段校验）。此前这三端仅有登录测试、无 profile 测试。
- **测试**：全量 201 tests / 586 assertions 通过；PHPStan level 8 无错；phpcs PSR12 零错零 warning。
- **文档**：`docs/union.md` 资料拉取小节补充飞书嵌套字段说明与全平台错误字段对照。

## v1.19.0

### 真实对接增强：企业微信用户资料适配器（修复错误路由）

- **新增 `WeWorkUserAdapter`**：企业微信此前根本没有用户资料适配器，`Union::profile(Channel::WechatWork, ...)` 被错误路由到**微信（Wechat）适配器**，导致要么调用错误的 Provider、要么静默返回空资料。现新增独立的 `WeWorkUserAdapter`，以登录阶段规范化的 `userid` 为键调 `cgi-bin/user/get` 拉取成员真实资料（姓名、头像、部门、职位等）。
- **修复 `Union::buildUserAdapter` 路由**：`Channel::WechatWork` 从映射到 `WechatUserAdapter` 改为正确映射到 `WeWorkUserAdapter`。
- **错误校验对齐**：企业微信 `user/get` 返回 `errcode != 0`（如 `60111 userid not found`）时统一抛 `ApiException`，与微信系及其他平台行为一致。
- **测试**：`tests/Union/UserProfileTest.php` 新增 3 个企业微信用例（成功拉取 + 归一化、显式 `userid` 覆盖、无效 userid 真实抛错），全量 195 tests / 567 assertions 通过。

## v1.18.0

### 真实对接增强：抖音 / QQ / 百度 用户资料真实拉取

- **补齐三端 `profile()` 的静默欠交付**：此前抖音 / QQ / 百度的 `UserAdapter::profile()` 直接返回空 `raw`，并未真实拉取用户资料。现已分别接入各平台真实接口：
  - 抖音：`Auth::userInfo()` 调用 `apps/v2/user/get_profile`，使用 app access_token + openid；未传 `access_token` 时自动回退到服务端 app token。
  - QQ：`Auth::userInfo()` 调用 `graph.qq.com/user/get_user_info`，错误字段为 `ret`（与登录接口的 `errcode` 不同），单独判断并抛 `ApiException`。
  - 百度：`Auth::userInfo()` 调用 `smartapp/getuserinfo`，错误字段为 `errno`（与授权接口的 `error` 不同），单独判断并抛 `ApiException`。
- **统一用户字段归一化增强**：`UnionUser::fromRaw()` 头像候选键新增 `figureurl_qq_2` / `figureurl_qq_1`，修正 QQ 资料头像（`figureurl_qq_2`）丢失为空的问题。
- **用户资料端到端测试 `tests/Union/UserProfileTest.php`**：覆盖抖音 / QQ / 百度三端。验证：① 成功拉取并归一化昵称 / 头像 / 性别 / union_id（抖音）；② 抖音未传 token 自动回退 app token；③ 三端在 token / 授权失效时真实抛出 `ApiException`；④ QQ / 百度未传 `access_token` 时优雅返回空 `raw`（不发起请求）。

### 质量保障

- 全量测试 192 个 / 560 断言通过；PHPStan level 8、PSR-12（phpcs）全部通过。

## v1.17.0

### 真实对接增强：全平台登录错误校验补齐 + 测试全覆盖

- **支付宝错误响应被静默吞掉的 bug 修复（关键）**：`ApiResponse` 此前仅识别 `alipay_*_response` 成功节点，而支付宝错误响应挂在独立的 `error_response` 节点（不以 `alipay_` 开头）。这导致支付宝授权失败 / 无效 `auth_code` 被当成成功、登录静默落入空数据。现新增 `alipayErrorResponse()` 专门识别 `error_response`，`code != 10000` 时统一抛 `ApiException`，与微信系行为对齐。
- **用户资料昵称归一化补全**：`UnionUser::fromRaw()` 的昵称候选键新增 `nick_name` / `user_name`，修正支付宝用户资料（`nick_name`）昵称丢失为空的问题。
- **全平台登录端到端测试 `tests/Union/PlatformLoginTest.php`**：覆盖支付宝 / 抖音 / QQ / 百度 / 企业微信 / 钉钉 / 飞书 共 7 个渠道。每个渠道均验证：① 成功登录正确提取 `openid` 与 `unionid`（支付宝无 unionid）；② 支付宝登录后拉取用户资料并归一化昵称 / 头像；③ 无效 `code` 在各自错误字段（抖音 `err_no` / QQ `errcode` / 百度 `error` / 企业微信 `errcode` / 钉钉 `errcode` / 飞书 `code` / 支付宝 `error_response`）下真实抛出 `ApiException`。至此微信系与全平台登录均具备端到端测试，杜绝静默失败。

### 质量保障

- 全量测试 183 个 / 533 断言通过；PHPStan level 8、PSR-12（phpcs）全部通过。

## v1.16.0

### 真实对接增强：微信各端登录错误校验与端到端测试

- **开放平台登录 `OpenApp` 真实对接校验**：`accessToken()` / `userInfo()` / `refreshToken()` / `authCheck()` 统一经 `ApiResponse` 归一化；`accessToken()` 在微信返回 `errcode`（如 `40029 invalid code`）时抛 `ApiException`，杜绝「无效 code 静默落入空 openid」。修正 `mobileAccessToken()` 调用 `accessToken()` 时参数顺序颠倒的真实集成 bug。
- **公众号 `MpLoginAdapter` 一致性**：`code` 换 `access_token` 改为经 `ApiResponse::throwIfFailed()` 校验微信错误，与小程序 / App / PC 一样抛出 `ApiException`（此前为不一致的 `RuntimeException`）。
- **第三方平台授权 `ComponentLoginAdapter` 健壮性**：`authorization_code` 换 `authorizer_access_token` 失败时抛 `RuntimeException`（含 `errmsg`），避免授权失败静默落入空 `UnionUser`。`Component::queryAuth()` 与 `WechatUser::info()` 改为经 `ApiResponse` 归一化，非法 JSON 不再触发 `TypeError`。
- **用户信息资料容错**：`WechatUserAdapter::profile()` 对 mp 路径与开放平台 App/PC 路径统一判定 `errcode`，错误体不再被当作有效资料。
- **端到端测试 `tests/Union/OpenPlatformLoginTest.php`**：按微信真实端点路由的 Mock HttpClient，验证小程序 / 公众号 / App / PC 登录返回正确的 `openid` 与 `unionId`、跨端 `unionId` 一致、公众号 / App / PC 资料拉取、以及无效 code 与授权失败真实抛错。

## v1.15.0

### 微信开放平台绑定：一键登录 + 用户信息

- **统一登录入口 `Union::wechat()` 强化**：公众号（`mp`）、小程序（`mini`）、H5（`h5`）、PC 网站应用（`pc`）、移动 App（`app`）均通过一行代码完成一键登录，并自动返回 `unionId`（同一开放平台下绑定各应用共享），实现"一键登录、多端通用"的跨端账号关联。
- **用户信息获取 `WechatUserAdapter::profile()` 修复与增强**：
  - 公众号 / H5：不再要求业务侧手动传入 `access_token`，适配器自动解析 mp access_token 调用 `cgi-bin/user/info`，正确返回 `unionId` + 昵称 + 头像 + 性别 + 地区。
  - 小程序：服务端无法拉取资料，支持传入客户端上报（已解密）的 `raw` 数据归一化。
  - 开放平台移动 / 网站应用（App / PC）：支持传入登录时获取的 OAuth `access_token` 经开放平台 Provider 拉取资料。
- **公众号 OAuth 登录 `MpLoginAdapter` 健壮性**：`snsapi_base` 静默授权（无用户资料）时不再把 `48001` 错误体当作用户资料，仅在 `snsapi_userinfo` 授权成功时才填充 `raw`。
- 新增 `tests/Union/WechatLoginTest`：覆盖公众号 / 小程序一键登录均返回相同 `unionId`、公众号资料自动拉取，证明开放平台绑定下的跨端关联与用户信息获取正确。

### 质量保障

- 全量测试 158 个 / 466 断言通过；PHPStan level 8、PSR-12（phpcs）全部通过。

### 兼容性

- 最低 PHP 要求保持 `>= 8.3`，无破坏性变更。

## v1.14.0

### 核心增强

- **统一 API 响应 `ApiResponse`**：归一化各平台异构错误字段（微信 errcode / 抖音 err_no / 百度 errno / 飞书 code / 支付宝 xxx_response.code / OAuth error），提供 `isSuccessful()`、`errorCode()`、`errorMessage()`、`throwIfFailed()`、`get/has/array/string/int`、`payload()` 等统一 API，并兼容 `ArrayAccess` 与 `JsonSerializable`。
- **业务异常 `ApiException`**：新增令牌失效（`isTokenInvalid`）、频率限制（`isRateLimited`）、可重试（`isRetryable`）分类，便于业务侧自动清缓存 / 退避。
- **令牌缓存 `TokenManager` + `TokenResult`**：AccessToken 默认走 PSR-16 缓存，带安全边界提前过期（默认 300s）与单飞锁（single-flight）防缓存击穿；支持 `remember / refresh / forget / has`。
- **HTTP 客户端增强 `HttpClient`**：可配置重试（指数退避 + 全抖动 + Retry-After 遵循）、敏感信息日志脱敏（`LogSanitizer`：secret / access_token / sign 等一律掩码）、自定义中间件注入、`json()` 便捷方法。
- **支付宝网关 `AlipayGateway`**：收敛此前散落在 6 个业务模块中的 `buildParams()/sign()`，修正两处签名缺陷——签名串需剔除空值与 `sign` 字段；`grant_type/code/auth_token` 属顶层参数而非 `biz_content`。私钥兼容 PKCS#1 与 PKCS#8。
- **9 个平台 Auth 模块接入**：微信 / QQ / 企业微信 / 钉钉 / 飞书 / 抖音 / 百度 / 微信开放平台 / 支付宝 的登录与令牌获取统一走 `ApiResponse` + `TokenManager`，避免每次调用重复换取导致配额耗尽。

### 质量保障

- 新增 `tests/Core/*` 与 `tests/Providers/Wechat/AuthTokenCacheTest` 单元测试，覆盖响应归一化、异常分类、重试策略、日志脱敏、令牌缓存与单飞、Auth 令牌缓存集成。
- 全量测试 154 个 / 451 断言通过；PHPStan level 8、PSR-12（phpcs）全部通过。

### 兼容性

- 最低 PHP 要求保持 `>= 8.3`。
- `HttpClientInterface` 既有方法签名保持不变，下游无破坏性变更；`AccessToken` 在内部改为委托 `TokenManager`。
