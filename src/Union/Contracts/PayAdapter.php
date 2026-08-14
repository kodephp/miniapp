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
 * ⚠️ 支付能力归属说明（软移交）：本包内的支付适配器属于「历史保留实现」，
 * 与登录 / 用户体系共用同一套各平台 appid / appsecret 凭证配置。
 * 新项目建议统一使用 {@see https://github.com/kodephp/pays kode/pays}（企业级多平台聚合支付 SDK）
 * 承载下单、订单、对账、退款等支付能力；本包支付适配器仅作向后兼容保留，
 * 后续可能标记为 deprecated 并最终移交，请勿在新代码中依赖本接口。
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
