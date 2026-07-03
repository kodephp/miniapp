<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;

/**
 * 支付适配器接口
 *
 * 统一封装各平台（小程序支付、公众号支付、APP 支付等）下单流程，
 * 业务侧不需要关心不同平台下单参数差异。
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
