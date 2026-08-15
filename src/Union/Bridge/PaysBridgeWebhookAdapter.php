<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\WebhookAdapter;

/**
 * kode/pays 桥接 Webhook 事件适配器（与 {@see PaysBridgeNotifyAdapter} 对称）
 *
 * 把 miniapp 服务端的「异步事件 Webhook 验签 / 解析」交给 kode/pays 完成，与
 * {@see \Kode\MiniApp\Union\Union::pay()} 共用同一套凭证与渠道映射。
 *
 * 与 {@see PaysBridgeNotifyAdapter}（同步支付结果通知：{@see \Kode\Pays\Contract\GatewayInterface::verifyNotify()}
 * 收「已解析数组」并解密）不同，本适配器面向「事件型 Webhook」：接收「原始请求体字符串 + 请求头」，
 * 委托 kode/pays 网关的 {@see \Kode\Pays\Contract\WebhookCapableInterface::verifyWebhook()} /
 * {@see \Kode\Pays\Contract\WebhookCapableInterface::parseWebhook()}。
 *
 * 设计要点：
 *  - 2.0 起本适配器是 Webhook 事件验签 / 解析的**唯一**实现（{@see WebhookAdapter} 契约）。
 *    其 `verify()` / `parse()` 直接委托底层 kode/pays 网关的 WebhookCapableInterface 方法，
 *    网关不支持时抛清晰异常，绝不会触发「Call to undefined method」。
 *  - 复用同一个 kode/pays 网关实例（与 `Union::pay()` 同一个 resolver），无需重复拼装 config。
 *  - 未安装 kode/pays、或当前渠道网关未实现 WebhookCapableInterface 时调用即抛清晰异常，
 *    引导业务侧先 `composer require kode/pays` 或换用支持的渠道。
 *
 * 典型用法：
 * ```php
 * // 装了 kode/pays：Webhook 验签 + 解析一步到位（推荐）
 * $wh = $kernel->union()->wechat()->webhook();
 * if (!$wh->verify($rawBody, $headers)) {
 *     http_response_code(400);
 *     exit;
 * }
 * $event = $wh->parse($rawBody);   // ['gateway'=>..., 'event_type'=>..., 'data'=>...]
 * ```
 */
final class PaysBridgeWebhookAdapter implements WebhookAdapter
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
     * 验证 Webhook 原始请求签名（委托 kode/pays 网关 verifyWebhook）
     *
     * @param string              $payload 原始请求体（未解码字符串）
     * @param array<string, string> $headers 请求头（含平台签名头）
     * @return bool 验签是否通过
     */
    #[\Override]
    public function verify(string $payload, array $headers = []): bool
    {
        /** @var mixed $gateway */
        $gateway = $this->pay->gateway();

        if (!is_object($gateway) || !method_exists($gateway, 'verifyWebhook')) {
            throw new \RuntimeException(
                "渠道 [{$this->channel->label()}] 的支付网关不支持 [Webhook 事件] 能力（未实现 verifyWebhook）",
            );
        }

        /** @var callable(string, array<string, string>):bool $fn */
        $fn = [$gateway, 'verifyWebhook'];

        return $fn($payload, $headers);
    }

    /**
     * 解析 Webhook 原始请求体为统一事件结构（委托 kode/pays 网关 parseWebhook）
     *
     * @param string $payload 原始请求体（JSON 字符串）
     * @return array<string, mixed> 统一事件结构（gateway / event_id / event_type / data / raw）
     */
    #[\Override]
    public function parse(string $payload): array
    {
        /** @var mixed $gateway */
        $gateway = $this->pay->gateway();

        if (!is_object($gateway) || !method_exists($gateway, 'parseWebhook')) {
            throw new \RuntimeException(
                "渠道 [{$this->channel->label()}] 的支付网关不支持 [Webhook 事件] 能力（未实现 parseWebhook）",
            );
        }

        /** @var callable(string):array<string, mixed> $fn */
        $fn = [$gateway, 'parseWebhook'];

        /** @var array<string, mixed> $result */
        $result = $fn($payload);

        return $result;
    }
}
