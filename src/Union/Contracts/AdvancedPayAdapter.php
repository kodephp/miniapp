<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

/**
 * 高级支付能力契约（分账 / 转账 / 对账）
 *
 * 扩展 {@see PayAdapter} 核心下单 / 退款 / 回调能力，暴露 kode/pays 网关的「特色方法」
 * （分账、转账、对账）。这些能力并非所有平台 / 网关都具备（例如百度、企业微信网关未实现，
 * 微信分账需先开通），因此单独抽成子接口，避免污染核心 {@see PayAdapter} 契约——
 * 业务侧按需通过 {@see \Kode\MiniApp\Union\Platforms\PlatformUnion::advancedPay()}
 * 取得本接口实例后调用。
 *
 * 方法命名与参数顺序**刻意对齐 kode/pays 网关契约**与 {@see \Kode\Pays\Facade\Pay} 统一入口
 * （createProfitSharing / singleTransfer / downloadBill ...），无额外封装、无参数变换：
 *
 *  - 分账（ProfitSharingCapableInterface）：发起 / 查询 / 回退 / 查询回退 / 解冻剩余资金
 *  - 转账（TransferCapableInterface）：单笔 / 批量 / 查询 / 电子回单
 *  - 对账（ReconciliationCapableInterface）：下载交易对账单 / 下载资金账单 / 解析对账单
 *
 * 调用前无需关心底层实现：本接口的唯一实现 {@see \Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter}
 * 会以 `method_exists` 守卫委托真实网关的特色方法，网关不支持某项能力时抛清晰异常。
 *
 * 能力发现：部分平台 / 网关并不支持全部能力（例如百度、企业微信网关未实现，QQ 不支持分账 /
 * 转账 / 对账，抖音仅支持分账）。调用前可用 {@see self::supportsProfitSharing()} /
 * {@see self::supportsTransfer()} / {@see self::supportsReconciliation()} 优雅判断，避免依赖
 * 捕获异常来决定分支。
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
}
