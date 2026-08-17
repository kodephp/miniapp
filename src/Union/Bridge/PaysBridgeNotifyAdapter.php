<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\NotifyAdapter;

/**
 * kode/pays 桥接回调适配器（与 {@see PaysBridgePayAdapter} 对称）
 *
 * 把 miniapp 服务端的「支付回调验签 / 解密」交给 kode/pays 完成，与
 * {@see Union::pay()} 共用同一套凭证与渠道映射——下单走 pays、回调验签也走 pays，
 * 支付全生命周期都落在 kode/pays（「收钱」层）。
 *
 * 设计要点：
 *  - 2.0 起本适配器是回调验签的**唯一**实现（{@see NotifyAdapter} 契约）。其 `decode()`
 *    直接委托 {@see PaysBridgePayAdapter::verifyNotify()}：调 kode/pays `verifyNotify` 验签，
 *    微信 V3 密文再经 `decryptResource` 解密，**返回可信业务数组**。
 *  - 复用同一个 kode/pays 网关实例（与 `Union::pay()` 同一个 resolver），无需重复拼装 config。
 *  - 未安装 kode/pays 时调用即抛清晰异常，引导业务侧先 `composer require kode/pays`。
 *
 * 典型用法：
 * ```php
 * // 装了 kode/pays：回调验签 + 解密一步到位（推荐）
 * $data = $kernel->union()->wechat()->notify()->decode($payload, $headers);
 * ```
 */
final class PaysBridgeNotifyAdapter implements NotifyAdapter
{
    public function __construct(
        private readonly Channel $channel,
        private readonly PaysBridgePayAdapter $pay,
    ) {
    }

    #[\Override]
    public function channel(): Channel
    {
        return $this->channel;
    }

    /**
     * 验证并解析回调数据（委托 kode/pays 验签 + 解密）
     *
     * @param array<string, mixed> $payload 平台 POST 推送的原始数据
     * @param array<string, string> $headers 平台 HTTP 头（用于签名校验）
     * @return array<string, mixed> 验签通过且解密后的业务数据（out_trade_no / transaction_id 等）
     */
    #[\Override]
    public function decode(array $payload, array $headers = []): array
    {
        return $this->pay->verifyNotify($payload, $headers);
    }

    /**
     * 验证 Webhook 原始通知（验签 + 解密，委托 kode/pays）
     *
     * @param string $rawBody 原始请求体（JSON 字符串）
     * @param array<string, string> $headers 平台 HTTP 头（用于签名校验）
     * @return array<string, mixed>
     */
    #[\Override]
    public function verifyWebhook(string $rawBody, array $headers = []): array
    {
        return $this->pay->verifyWebhook($rawBody, $headers);
    }
}
