<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 支付适配器接口（对外契约，方法名对齐 kode/pays）
 *
 * 统一封装各平台下单 / 查询 / 退款 / 关单 / 回调验签流程，业务侧不需要关心平台差异。
 * 本接口的方法名与参数顺序**刻意对齐 kode/pays 网关契约**（createOrder / queryOrder /
 * refund / queryRefund / closeOrder / verifyNotify）。2.0 起支付能力完全委托 kode/pays，
 * 本接口的唯一实现 {@see \Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter} 即 pays 的薄桥接，
 * 调用代码与直接使用 kode/pays 完全一致。
 *
 * 登录与支付强绑定（平台硬约束）：各平台支付都依赖本平台登录得到的用户标识
 * （如微信 JSAPI 的 openid、支付宝的 buyer_user_id）。{@see self::createOrder()} 额外接收
 * 可选的 {@see UnionUser} 参数（pays 同名方法只有 $params，这是本接口作为「超集」保留的能力），
 * 由桥接在需要时自动注入下单参数，缺失时 fail-fast。无需该标识的交易类型可忽略之。
 *
 * 与 kode/pays 的分工（miniapp 只管身份，收钱交给 pays）：
 *  - 本包（miniapp）=「登录 + 平台身份」层：产出 {@see UnionUser}（付款人身份）。
 *  - kode/pays = 支付归属层：承载下单编排、回调验签、退款、对账、分账、沙箱、多渠道聚合。
 *    它**不处理登录、不知道 miniapp 的存在**——付款人只是其 `createOrder(array $params)` 里的
 *    原生字段（openid / buyer_id），由本包登录流程提供后由桥接翻译注入。
 *  - 本接口的唯一实现 {@see \Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter}：透传全部能力到
 *    kode/pays；{@see \Kode\MiniApp\Union\Platforms\PlatformUnion::pay()} 直接返回它（需先
 *    `composer require kode/pays`）。
 *  - 分账 / 转账 / 对账 / 红包 / 订阅 / 余额 / 结算等高级能力不在本核心契约内（并非所有网关都
 *    支持），改由子接口 {@see \Kode\MiniApp\Union\Contracts\AdvancedPayAdapter} 提供，业务侧通过
 *    {@see \Kode\MiniApp\Union\Platforms\PlatformUnion::advancedPay()} 取得。
 *
 * 回调验签语义对齐：pays 的 `verifyNotify` 返回 bool（仅验签），本接口补全为
 * 「验签通过则返回解析后的业务数据数组，验签失败抛异常」，与 kode/pays verifyNotify 语义一致，
 * 业务侧统一按数组拿 out_trade_no / transaction_id。
 */
interface PayAdapter
{
    public function channel(): Channel;

    /**
     * 统一下单（对齐 kode/pays 网关 createOrder）
     *
     * 返回平台原始的支付参数（如微信 prepay_id / 签名，支付宝 orderStr）。
     *
     * @param array<string, mixed> $order 统一下单参数（与 kode/pays createOrder 的 $params 同构）
     * @param UnionUser|null       $user  可选，已登录用户；用于自动注入平台支付所需的用户标识
     *                                    （如微信 JSAPI 的 openid）。传 null 时业务侧须自行提供该标识。
     *                                    本参数是本接口相对 pays 的「超集」，pays 同名方法无此参数。
     * @return array<string, mixed> 平台原始响应
     */
    public function createOrder(array $order, ?UnionUser $user = null): array;

    /**
     * 查询订单（对齐 kode/pays 网关 queryOrder）
     */
    /** @return array<string, mixed> */
    public function queryOrder(string $orderId): array;

    /**
     * 申请退款（对齐 kode/pays 网关 refund）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function refund(array $params): array;

    /**
     * 查询退款（对齐 kode/pays 网关 queryRefund）
     */
    /** @return array<string, mixed> */
    public function queryRefund(string $refundId): array;

    /**
     * 关闭订单（对齐 kode/pays 网关 closeOrder）
     */
    /** @return array<string, mixed> */
    public function closeOrder(string $orderId): array;

    /**
     * 回调验签（对齐 kode/pays 网关 verifyNotify）
     *
     * 验签通过返回**解析后的业务数据数组**（out_trade_no / transaction_id 等），
     * 验签失败抛异常。业务侧统一按数组取回业务字段，与 kode/pays verifyNotify 语义一致。
     *
     * @param array<string, mixed> $payload 平台 POST 推送原始数据
     * @param array<string, string> $headers 平台 HTTP 头（用于签名校验）
     * @return array<string, mixed> 解析后的业务数据
     */
    public function verifyNotify(array $payload, array $headers = []): array;
}
