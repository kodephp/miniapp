<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;

/**
 * 支付适配器接口
 *
 * 统一封装各平台（小程序支付、公众号支付、APP 支付等）下单流程，
 * 业务侧不需要关心不同平台下单参数差异。
 *
 * ⚠️ 支付能力归属说明（软移交）：本包内的支付适配器属于「历史保留实现」，
 * 与登录 / 用户体系共用同一套各平台 appid / appsecret 凭证配置。
 * 新项目建议统一使用 {@see https://github.com/kode-lab/pays kode/pays}（企业级多平台聚合支付 SDK）
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
     * @return array<string, mixed> 平台原始响应
     */
    public function unifiedOrder(array $order): array;
}
