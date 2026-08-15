<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\CryptoAdapter;

/**
 * kode/pays 桥接加密货币适配器（与 {@see PaysBridgeNotifyAdapter} / {@see PaysBridgeWebhookAdapter}
 * / {@see PaysBridgeRefundAdapter} 对称）
 *
 * 把加密货币支付（Coinbase 等聚合网关）交给 kode/pays 完成，与 {@see \Kode\MiniApp\Union\Union::pay()}
 * 共用同一套凭证与渠道映射。加密货币方法名与核心 {@see \Kode\MiniApp\Union\Contracts\PayAdapter}
 * 重名（createOrder / queryOrder / refund / verifyNotify），故单独成适配器，避免污染下单链路。
 *
 * 本适配器暴露 kode/pays 网关 {@see \Kode\Pays\Contract\CryptoCapableInterface} 的完整能力：
 * 法币 / 币种定价下单、支付地址、链上确认、实时汇率、订单状态、退款、异步验签。
 *
 * 设计要点：
 *  - 以 `method_exists` 守卫委托真实 kode/pays 网关的加密货币方法，网关不支持时抛清晰异常，
 *    绝不会触发「Call to undefined method」。
 *  - 复用同一个 kode/pays 网关实例（与 `Union::pay()` 同一个 resolver），无需重复拼装 config。
 *  - 未安装 kode/pays、或当前渠道网关未实现对应方法时调用即抛清晰异常，
 *    引导业务侧先 `composer require kode/pays` 或换用支持的渠道（如 Coinbase）。
 *
 * 典型用法：
 * ```php
 * $crypto = $kernel->union()->crypto(Channel::Crypto, fn () => ['api_key' => '...']);
 * $order  = $crypto->createCryptoOrder(['crypto_currency' => 'BTC', 'fiat_amount' => 100]);
 * $info   = $crypto->getPaymentAddresses($order['id']);
 * $rate   = $crypto->getExchangeRate('BTC', 'USD');
 * ```
 */
final class PaysBridgeCryptoAdapter implements CryptoAdapter
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
     * 创建法币定价订单（委托 kode/pays 网关 createOrder）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function createOrder(array $params): array
    {
        return $this->callCryptoFeature('createOrder', '加密货币下单', $params);
    }

    /**
     * 创建指定币种定价订单（委托 kode/pays 网关 createCryptoOrder）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function createCryptoOrder(array $params): array
    {
        return $this->callCryptoFeature('createCryptoOrder', '加密货币下单', $params);
    }

    /**
     * 获取支付地址（委托 kode/pays 网关 getPaymentAddresses）
     *
     * @param string $orderId
     * @return array<string, mixed>
     */
    #[\Override]
    public function getPaymentAddresses(string $orderId): array
    {
        return $this->callCryptoFeature('getPaymentAddresses', '加密货币地址', $orderId);
    }

    /**
     * 查询链上确认（委托 kode/pays 网关 getConfirmations）
     *
     * @param string $orderId
     * @return array<string, mixed>
     */
    #[\Override]
    public function getConfirmations(string $orderId): array
    {
        return $this->callCryptoFeature('getConfirmations', '加密货币确认', $orderId);
    }

    /**
     * 实时汇率（委托 kode/pays 网关 getExchangeRate）
     *
     * @param string $cryptoCurrency
     * @param string $fiatCurrency
     * @return array<string, mixed>
     */
    #[\Override]
    public function getExchangeRate(string $cryptoCurrency, string $fiatCurrency = 'USD'): array
    {
        return $this->callCryptoFeature('getExchangeRate', '加密货币汇率', $cryptoCurrency, $fiatCurrency);
    }

    /**
     * 查询订单状态（委托 kode/pays 网关 queryOrder）
     *
     * @param string $orderId
     * @return array<string, mixed>
     */
    #[\Override]
    public function queryOrder(string $orderId): array
    {
        return $this->callCryptoFeature('queryOrder', '加密货币订单', $orderId);
    }

    /**
     * 退款（委托 kode/pays 网关 refund）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function refund(array $params): array
    {
        return $this->callCryptoFeature('refund', '加密货币退款', $params);
    }

    /**
     * 验证异步通知（委托 kode/pays 网关 verifyNotify，返回 bool）
     *
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function verifyNotify(array $data): bool
    {
        /** @var mixed $gateway */
        $gateway = $this->pay->gateway();

        if (!is_object($gateway) || !method_exists($gateway, 'verifyNotify')) {
            throw new \RuntimeException(
                "渠道 [{$this->channel->label()}] 的支付网关不支持 [加密货币异步验签] 能力（未实现 verifyNotify）",
            );
        }

        /** @var callable(array<string, mixed>):bool $fn */
        $fn = [$gateway, 'verifyNotify'];

        return $fn($data);
    }

    /**
     * 委托真实 kode/pays 网关的加密货币方法（返回 array 的一组）
     *
     * 以 `method_exists` 守卫：仅当当前渠道的网关真正实现了该方法时才转发，
     * 否则抛清晰异常，避免「Call to undefined method」。
     *
     * @param string       $method      网关原生方法名
     * @param string       $capability  能力中文名（用于异常提示）
     * @param mixed        ...$args     透传给网关方法的参数
     * @return array<string, mixed>
     */
    private function callCryptoFeature(string $method, string $capability, mixed ...$args): array
    {
        /** @var mixed $gateway */
        $gateway = $this->pay->gateway();

        if (!is_object($gateway) || !method_exists($gateway, $method)) {
            throw new \RuntimeException(
                "渠道 [{$this->channel->label()}] 的支付网关不支持 [{$capability}] 能力（未实现 {$method}）",
            );
        }

        /** @var callable(mixed...):array<string, mixed> $fn */
        $fn = [$gateway, $method];

        /** @var array<string, mixed> $result */
        $result = $fn(...$args);

        return $result;
    }
}
