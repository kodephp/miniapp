<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;

/**
 * 异步 Webhook 事件回调适配器接口
 *
 * 与 {@see NotifyAdapter}（同步支付结果通知，验签 + 解密「已解析数组」）不同，本接口面向
 * 平台异步推送的「事件型 Webhook」（订阅续费、退款状态变更、转账到账、争议 / 拒付、
 * 账户风控等）。它与运行时解耦，接收「原始请求体字符串 + 请求头」，便于单元测试与中间层复用。
 *
 * 唯一实现 {@see \Kode\MiniApp\Union\Bridge\PaysBridgeWebhookAdapter}：委托 kode/pays 网关的
 * {@see \Kode\Pays\Contract\WebhookCapableInterface::verifyWebhook()} /
 * {@see \Kode\Pays\Contract\WebhookCapableInterface::parseWebhook()}，让验签 / 解析完全由
 * kode/pays 负责（「收钱」层），miniapp 只负责把原始报文转交。
 */
interface WebhookAdapter
{
    public function channel(): Channel;

    /**
     * 验证 Webhook 原始请求签名
     *
     * @param string              $payload 原始请求体（未解码字符串）
     * @param array<string, string> $headers 请求头（含平台签名头，如 Stripe-Signature）
     * @return bool 验签是否通过
     */
    public function verify(string $payload, array $headers = []): bool;

    /**
     * 解析 Webhook 原始请求体为统一事件结构
     *
     * 返回结构含：gateway（网关标识）、event_id（事件 ID）、event_type（事件类型）、
     * data（解码后的完整报文）、raw（原始报文）。
     *
     * @param string $payload 原始请求体（JSON 字符串）
     * @return array<string, mixed> 统一事件结构
     */
    public function parse(string $payload): array;
}
