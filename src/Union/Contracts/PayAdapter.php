<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 支付适配器接口
 *
 * 统一封装各平台（小程序支付、公众号支付、APP 支付等）下单流程，
 * 业务侧不需要关心不同平台下单参数差异。
 *
 * 登录与支付强绑定（平台硬约束）：各平台支付都依赖本平台登录得到的用户标识
 * （如微信 JSAPI 的 openid、支付宝的 buyer_user_id），因此 {@see self::unifiedOrder()}
 * 接收可选的 {@see UnionUser} 参数，由适配器在需要时提取对应标识自动注入下单参数，
 * 缺失时 fail-fast。无需该标识的交易类型（如微信 APP / H5 / NATIVE）可忽略之。
 *
 * 与 kode/pays 的关系（不是替代，而是分工）：
 *  - 本包（miniapp）=「登录 + 平台身份」层：负责 OAuth / code2session 登录、获取
 *    openid / unionid、用户体系，并内置一套已生产化（含 V3 签名、服务商模式）的「基础支付」。
 *  - kode/pays = 可选的「支付中枢」SDK：承载下单编排、回调验签、退款、对账、沙箱、多渠道聚合。
 *    它**不处理登录、拿不到 openid**——付款人标识仍需由本包的登录流程提供。
 *  - 二者是「身份 → 支付」的上下游：先用本包登录拿到 openid，再以相同凭证交给
 *    kode/pays 做支付编排。本包内置的 {@see \Kode\MiniApp\Union\Bridge\PaysBridge}
 *    可把 Kernel 凭证自动翻译为 kode/pays 网关 config，调用方式与本包基础支付完全一致。
 *  - 选择建议：只需下单 + 回调 → 用本包内置基础支付即可（已生产级）；需要退款 / 对账 /
 *    多支付渠道统一编排 → 安装 kode/pays 走 PaysBridge。两套入口返回的数组契约相同，
 *    业务侧可无损切换，不存在「必须二选一」。
 */
interface PayAdapter
{
    public function channel(): Channel;

    /**
     * 统一下单，返回平台原始的支付参数
     *
     * 例如：微信小程序返回 prepay_id / 签名参数，
     * 支付宝返回 orderStr，App 返回预下单字符串等。
     *
     * @param array<string, mixed> $order 统一下单参数
     * @param UnionUser|null       $user  可选，已登录用户；用于自动注入平台支付所需的
     *                                    用户标识（如微信 JSAPI 的 openid）。传 null 时
     *                                    业务侧须自行在 $order 中提供该标识。
     * @return array<string, mixed> 平台原始响应
     */
    public function unifiedOrder(array $order, ?UnionUser $user = null): array;
}
