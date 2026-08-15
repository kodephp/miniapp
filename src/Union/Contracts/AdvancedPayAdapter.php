<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

/**
 * 高级支付能力契约（分账 / 转账 / 对账 / 红包 / 订阅 / 余额 / 结算）
 *
 * 扩展 {@see PayAdapter} 核心下单 / 退款 / 回调能力，暴露 kode/pays 网关的「特色方法」。
 * 这些能力并非所有平台 / 网关都具备（例如百度、企业微信网关未实现，微信 V2 不支持余额查询），
 * 因此单独抽成子接口，避免污染核心 {@see PayAdapter} 契约——业务侧按需通过
 * {@see \Kode\MiniApp\Union\Platforms\PlatformUnion::advancedPay()} 取得本接口实例后调用。
 *
 * 方法命名与参数顺序**刻意对齐 kode/pays 网关契约**与 {@see \Kode\Pays\Facade\Pay} 统一入口，
 * 无额外封装、无参数变换：
 *
 *  - 分账（ProfitSharingCapableInterface）：发起 / 查询 / 回退 / 查询回退 / 解冻剩余资金
 *  - 转账（TransferCapableInterface）：单笔 / 批量 / 查询 / 电子回单
 *  - 对账（ReconciliationCapableInterface）：下载交易对账单 / 下载资金账单 / 解析对账单
 *  - 红包（RedPacketCapableInterface）：普通红包 / 裂变红包 / 查询红包记录
 *  - 订阅（SubscriptionCapableInterface）：创建订阅计划 / 发起订阅 / 取消 / 暂停 / 恢复 / 查询
 *  - 余额（BalanceCapableInterface）：查询账户余额 / 查询日终余额
 *  - 结算（SettlementCapableInterface）：结算到钱包 / 银行卡 / 代付 / 查询结算单
 *
 * 调用前无需关心底层实现：本接口的唯一实现 {@see \Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter}
 * 会以 `method_exists` 守卫委托真实网关的特色方法，网关不支持某项能力时抛清晰异常。
 *
 * 能力发现：部分平台 / 网关并不支持全部能力（例如百度、企业微信网关未实现，QQ 不支持分账 /
 * 转账 / 对账，微信 V2 不支持余额查询，抖音仅支持分账）。调用前可用 {@see self::supports*()}
 * 系列方法优雅判断，避免依赖捕获异常来决定分支。
 */
interface AdvancedPayAdapter extends PayAdapter
{
    /**
     * 发起分账（对齐 kode/pays 网关 createProfitSharing）
     *
     * @param array<string, mixed> $params 分账参数（transaction_id / out_order_no / receivers 等）
     * @return array<string, mixed>
     */
    public function profitSharingCreate(array $params): array;

    /**
     * 查询分账结果（对齐 kode/pays 网关 queryProfitSharing）
     *
     * @param string      $outOrderNo     商户分账订单号
     * @param string|null $transactionId  原支付订单号（微信必填，其余平台忽略）
     * @return array<string, mixed>
     */
    public function profitSharingQuery(string $outOrderNo, ?string $transactionId = null): array;

    /**
     * 分账回退（对齐 kode/pays 网关 returnProfitSharing）
     *
     * @param array<string, mixed> $params 回退参数（out_order_no / out_return_no / return_amount 等）
     * @return array<string, mixed>
     */
    public function profitSharingReturn(array $params): array;

    /**
     * 查询分账回退结果（对齐 kode/pays 网关 queryProfitSharingReturn）
     *
     * @param string $outReturnNo 商户回退单号
     * @return array<string, mixed>
     */
    public function profitSharingQueryReturn(string $outReturnNo): array;

    /**
     * 解冻未分账的剩余资金（对齐 kode/pays 网关 unfreezeProfitSharing）
     *
     * @param string      $transactionId 原支付订单号
     * @param string|null $outOrderNo    商户解冻单号（可选，缺省由网关自动生成）
     * @return array<string, mixed>
     */
    public function profitSharingUnfreeze(string $transactionId, ?string $outOrderNo = null): array;

    /**
     * 发起单笔转账 / 企业付款到零钱（对齐 kode/pays 网关 singleTransfer）
     *
     * @param array<string, mixed> $params 转账参数（out_biz_no / amount / recipient / description 等）
     * @return array<string, mixed>
     */
    public function transferSingle(array $params): array;

    /**
     * 发起批量转账（对齐 kode/pays 网关 batchTransfer）
     *
     * @param array<string, mixed> $params 批量转账参数（out_biz_no / transfer_detail_list 等）
     * @return array<string, mixed>
     */
    public function transferBatch(array $params): array;

    /**
     * 查询转账结果（对齐 kode/pays 网关 queryTransfer）
     *
     * @param string $outBizNo 商户转账单号
     * @return array<string, mixed>
     */
    public function transferQuery(string $outBizNo): array;

    /**
     * 查询转账电子回单（对齐 kode/pays 网关 transferReceipt）
     *
     * @param string $outBizNo 商户转账单号
     * @return array<string, mixed>
     */
    public function transferReceipt(string $outBizNo): array;

    /**
     * 下载交易对账单（对齐 kode/pays 网关 downloadBill）
     *
     * @param array<string, mixed> $params 对账参数（bill_date 必填）
     * @return array<string, mixed> 含原始响应与解析后的记录列表
     */
    public function reconciliationDownloadBill(array $params): array;

    /**
     * 下载资金账单（对齐 kode/pays 网关 downloadFundFlow）
     *
     * @param array<string, mixed> $params 资金账单参数（bill_date 必填）
     * @return array<string, mixed>
     */
    public function reconciliationDownloadFundFlow(array $params): array;

    /**
     * 解析对账单原始数据（对齐 kode/pays 网关 parseBill）
     *
     * @param string $rawData 原始对账单数据（CSV / JSON）
     * @return array<int, array<string, mixed>> 解析后的交易记录列表
     */
    public function reconciliationParseBill(string $rawData): array;

    /**
     * 发放普通红包（对齐 kode/pays 网关 sendRedPacket）
     *
     * @param array<string, mixed> $params 红包参数
     *        （mch_billno / send_name / re_openid / total_amount / wishing / act_name / remark 等）
     * @return array<string, mixed>
     */
    public function redPacketSend(array $params): array;

    /**
     * 发放裂变红包（群红包，对齐 kode/pays 网关 groupRedPacket）
     *
     * @param array<string, mixed> $params 裂变红包参数（在普通红包基础上需 total_num >= 3）
     * @return array<string, mixed>
     */
    public function redPacketGroup(array $params): array;

    /**
     * 查询红包发放记录（对齐 kode/pays 网关 queryRedPacket）
     *
     * @param string $mchBillNo 商户红包订单号
     * @return array<string, mixed>
     */
    public function redPacketQuery(string $mchBillNo): array;

    /**
     * 创建订阅计划（对齐 kode/pays 网关 createPlan）
     *
     * @param array<string, mixed> $params 计划参数（product_id / name / amount / interval 等）
     * @return array<string, mixed>
     */
    public function subscriptionCreatePlan(array $params): array;

    /**
     * 发起订阅（签约并首次扣款，对齐 kode/pays 网关 createSubscription）
     *
     * @param array<string, mixed> $params 订阅参数（plan_id / out_trade_no / payer / amount 等）
     * @return array<string, mixed>
     */
    public function subscriptionSubscribe(array $params): array;

    /**
     * 取消订阅（对齐 kode/pays 网关 cancelSubscription）
     *
     * @param string $subscriptionId 订阅 ID
     * @return array<string, mixed>
     */
    public function subscriptionCancel(string $subscriptionId): array;

    /**
     * 暂停订阅（对齐 kode/pays 网关 pauseSubscription）
     *
     * @param string $subscriptionId 订阅 ID
     * @return array<string, mixed>
     */
    public function subscriptionPause(string $subscriptionId): array;

    /**
     * 恢复订阅（对齐 kode/pays 网关 resumeSubscription）
     *
     * @param string $subscriptionId 订阅 ID
     * @return array<string, mixed>
     */
    public function subscriptionResume(string $subscriptionId): array;

    /**
     * 查询订阅详情（对齐 kode/pays 网关 getSubscription）
     *
     * @param string $subscriptionId 订阅 ID
     * @return array<string, mixed>
     */
    public function subscriptionGet(string $subscriptionId): array;

    /**
     * 查询账户余额（对齐 kode/pays 网关 queryBalance）
     *
     * @param array<string, mixed> $params 查询参数（部分平台需 merchant_id / account_type 等）
     * @return array<string, mixed>
     */
    public function balanceQuery(array $params = []): array;

    /**
     * 查询日终余额（对账用，对齐 kode/pays 网关 queryDayEndBalance）
     *
     * @param string               $date   对账日期（Ymd）
     * @param array<string, mixed> $params 附加参数
     * @return array<string, mixed>
     */
    public function balanceQueryDayEnd(string $date, array $params = []): array;

    /**
     * 结算到钱包（对齐 kode/pays 网关 settleToWallet）
     *
     * @param array<string, mixed> $params 结算参数（out_biz_no / amount / account 等）
     * @return array<string, mixed>
     */
    public function settlementToWallet(array $params): array;

    /**
     * 结算到银行卡（对齐 kode/pays 网关 settleToBankCard）
     *
     * @param array<string, mixed> $params 结算参数（out_biz_no / amount / bank_account 等）
     * @return array<string, mixed>
     */
    public function settlementToBankCard(array $params): array;

    /**
     * 结算到代付（对齐 kode/pays 网关 settleToPayout）
     *
     * @param array<string, mixed> $params 结算参数
     * @return array<string, mixed>
     */
    public function settlementToPayout(array $params): array;

    /**
     * 查询结算单（对齐 kode/pays 网关 querySettlement）
     *
     * @param string $outBizNo 商户结算单号
     * @return array<string, mixed>
     */
    public function settlementQuery(string $outBizNo): array;

    // ===== 个人收款（PersonalReceiveCapableInterface） =====

    /**
     * 生成个人收款二维码（对齐 kode/pays 网关 createQrCode）
     *
     * 为个人 / 小微商户提供收款能力（无需企业资质）。
     *
     * @param array<string, mixed> $params 收款参数（amount / description 等）
     * @return array<string, mixed> 包含二维码 URL / 内容
     */
    public function personalReceiveCreateQrCode(array $params): array;

    /**
     * 查询个人收款记录（对齐 kode/pays 网关 queryRecords）
     *
     * @param array<string, mixed> $params 查询参数（start_time / end_time / page 等）
     * @return array<string, mixed> 收款记录列表
     */
    public function personalReceiveQueryRecords(array $params): array;

    /**
     * 提现到银行卡（对齐 kode/pays 网关 withdraw）
     *
     * @param array<string, mixed> $params 提现参数（amount / bank_account 等）
     * @return array<string, mixed>
     */
    public function personalReceiveWithdraw(array $params): array;

    /**
     * 查询提现结果（对齐 kode/pays 网关 queryWithdraw）
     *
     * @param string $outBizNo 商户提现单号
     * @return array<string, mixed>
     */
    public function personalReceiveQueryWithdraw(string $outBizNo): array;

    /**
     * 当前渠道是否支持「个人收款」能力
     *
     * 基于 kode/pays 网关类是否实现 PersonalReceiveCapableInterface 判断，无需完整支付配置即可调用。
     * 返回 false 时调用 {@see self::personalReceiveCreateQrCode()} 等会抛清晰异常。
     */
    public function supportsPersonalReceive(): bool;

    /**
     * 当前渠道是否支持「分账」能力
     *
     * 基于 kode/pays 网关类是否实现 ProfitSharingCapableInterface 判断，**无需完整支付配置**
     * 即可调用。返回 false 时调用 {@see self::profitSharingCreate()} 等会抛清晰异常。
     */
    public function supportsProfitSharing(): bool;

    /**
     * 当前渠道是否支持「转账」能力
     *
     * 基于 kode/pays 网关类是否实现 TransferCapableInterface 判断，无需完整支付配置即可调用。
     */
    public function supportsTransfer(): bool;

    /**
     * 当前渠道是否支持「对账」能力
     *
     * 基于 kode/pays 网关类是否实现 ReconciliationCapableInterface 判断，无需完整支付配置即可调用。
     */
    public function supportsReconciliation(): bool;

    /**
     * 当前渠道是否支持「红包」能力
     *
     * 基于 kode/pays 网关类是否实现 RedPacketCapableInterface 判断，无需完整支付配置即可调用。
     */
    public function supportsRedPacket(): bool;

    /**
     * 当前渠道是否支持「订阅」能力
     *
     * 基于 kode/pays 网关类是否实现 SubscriptionCapableInterface 判断，无需完整支付配置即可调用。
     */
    public function supportsSubscription(): bool;

    /**
     * 当前渠道是否支持「余额」能力
     *
     * 基于 kode/pays 网关类是否实现 BalanceCapableInterface 判断，无需完整支付配置即可调用。
     * 注意：微信 V2 网关不支持，微信 V3 / 支付宝 / Stripe 等支持。
     */
    public function supportsBalance(): bool;

    /**
     * 当前渠道是否支持「结算」能力
     *
     * 基于 kode/pays 网关类是否实现 SettlementCapableInterface 判断，无需完整支付配置即可调用。
     */
    public function supportsSettlement(): bool;

    /**
     * 当前渠道是否支持「Webhook 事件」能力
     *
     * 基于 kode/pays 网关类是否实现 WebhookCapableInterface 判断，无需完整支付配置即可调用。
     * 返回 false 时调用 {@see \Kode\MiniApp\Union\Contracts\WebhookAdapter::verify()} /
     * {@see \Kode\MiniApp\Union\Contracts\WebhookAdapter::parse()} 会抛清晰异常。
     */
    public function supportsWebhook(): bool;

    /**
     * 当前渠道是否支持「退款」能力（申请退款 / 查询退款 / 取消退款）
     *
     * 基于 kode/pays 网关类是否实现 RefundCapableInterface 判断，无需完整支付配置即可调用。
     * 注意：`cancelRefund` 仅部分网关支持（如 Stripe），故本方法以 `applyRefund` 作为能力基线；
     * 返回 false 时调用 {@see \Kode\MiniApp\Union\Contracts\RefundAdapter} 三个方法会抛清晰异常。
     */
    public function supportsRefund(): bool;
}
