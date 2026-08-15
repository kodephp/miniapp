<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;

/**
 * 加密货币支付能力适配器契约
 *
 * 对齐 kode/pays 网关 {@see \Kode\Pays\Contract\CryptoCapableInterface} 的加密货币支付能力
 * （Coinbase Commerce 等聚合网关）：法币 / 指定币种定价下单、获取支付地址、查询链上确认、
 * 实时汇率、订单状态、退款与异步通知验签。
 *
 * 与 {@see AdvancedPayAdapter}（分账 / 转账 / 红包 / 订阅 / 余额 / 结算 / 个人收款）不同，
 * 加密货币是一个**独立的支付形态**，其方法名与核心 {@see PayAdapter} 的 createOrder /
 * queryOrder / refund / verifyNotify 重名，故单独成契约，避免污染高级支付适配器。
 *
 * 2.0 起由 {@see \Kode\MiniApp\Union\Bridge\PaysBridgeCryptoAdapter} 作为唯一实现，
 * 以 `method_exists` 守卫委托真实 kode/pays 网关。网关不支持时抛清晰异常，绝不会触发
 * 「Call to undefined method」。
 *
 * 典型用法：
 * ```php
 * $crypto = $kernel->union()->crypto(Channel::Crypto);
 * $order  = $crypto->createCryptoOrder(['crypto_currency' => 'BTC', 'fiat_amount' => 100, 'fiat_currency' => 'USD']);
 * $rate   = $crypto->getExchangeRate('BTC', 'USD');
 * ```
 *
 * @see \Kode\MiniApp\Union\Bridge\PaysBridge
 */
interface CryptoAdapter
{
    /**
     * 获取适配的渠道（支付方式）
     */
    public function channel(): Channel;

    /**
     * 创建法币定价的加密货币订单（对齐 kode/pays 网关 createOrder）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createOrder(array $params): array;

    /**
     * 创建指定加密货币定价的订单（对齐 kode/pays 网关 createCryptoOrder）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createCryptoOrder(array $params): array;

    /**
     * 获取加密货币支付地址（对齐 kode/pays 网关 getPaymentAddresses）
     *
     * @param string $orderId Charge ID
     * @return array<string, mixed>
     */
    public function getPaymentAddresses(string $orderId): array;

    /**
     * 查询链上确认状态（对齐 kode/pays 网关 getConfirmations）
     *
     * @param string $orderId Charge ID
     * @return array<string, mixed>
     */
    public function getConfirmations(string $orderId): array;

    /**
     * 查询加密货币实时汇率（对齐 kode/pays 网关 getExchangeRate）
     *
     * @param string $cryptoCurrency 加密货币代码
     * @param string $fiatCurrency   法币代码（默认 USD）
     * @return array<string, mixed>
     */
    public function getExchangeRate(string $cryptoCurrency, string $fiatCurrency = 'USD'): array;

    /**
     * 查询订单状态（对齐 kode/pays 网关 queryOrder）
     *
     * @param string $orderId 订单 ID
     * @return array<string, mixed>
     */
    public function queryOrder(string $orderId): array;

    /**
     * 发起退款（对齐 kode/pays 网关 refund）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function refund(array $params): array;

    /**
     * 验证异步通知（对齐 kode/pays 网关 verifyNotify）
     *
     * @param array<string, mixed> $data 通知数据
     * @return bool 验证结果
     */
    public function verifyNotify(array $data): bool;
}
