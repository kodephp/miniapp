<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Bridge;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\AdvancedPayAdapter;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\UnionUser;
use Kode\Pays\Core\GatewayFactory;

/**
 * kode/pays 桥接支付适配器（首选支付实现）
 *
     * 把 miniapp 的「身份层」接入企业级聚合支付 SDK {@see https://github.com/kodephp/pays kode/pays}，
     * 让支付这件事完全由 kode/pays 负责（下单 / 查询 / 退款 / 关单 / 回调验签 / 对账 / 沙箱 / 分账 /
     * 转账 / 红包 / 订阅 / 余额 / 结算 / 事件），而不必在 miniapp 内重复实现一套支付逻辑。
     *
     * 除核心下单 / 退款 / 关单 / 验签外，本适配器还实现 {@see AdvancedPayAdapter}，以 `method_exists`
     * 守卫委托 kode/pays 网关的「特色方法」暴露分账 / 转账 / 对账 / 红包 / 订阅 / 余额 / 结算等高级能力；
     * 网关不支持时抛清晰异常。
 *
 * 分工（参照 kode/miniapp =「你是谁」、kode/pays =「收钱」）：
 *  - miniapp 负责身份：OAuth / code2session 登录、产出 {@see UnionUser}（openid / unionid /
 *    session_key / access_token / JS-SDK 票据）。它**不**碰签名、订单、回调、退款、分账。
 *  - kode/pays 负责收钱：以平台原生 `createOrder(array $params)` 形式接收订单与付款人字段，
 *    **不**依赖 miniapp 类型（对纯 Web / Stripe 后端零耦合）。其微信网关在 JSAPI / 小程序下单时
 *    强制要求 `openid`，且源码注释明确「openid 来自 kode/miniapp 等 OAuth 授权」。
 *  - 本桥接是二者之间**唯一、可选的单向胶水**，落在 miniapp 一侧：把 {@see UnionUser} 的
 *    付款人标识（openid / buyer_id）翻译为 kode/pays 的原生 `$params` 字段后下单。
 *    kode/pays 永远不知道 miniapp 的存在；miniapp 知道 kode/pays 仅是「可选增强」。
 *
 * 命名对齐：本适配器方法名与 kode/pays 网关契约**完全一致**（createOrder / queryOrder /
 * refund / queryRefund / closeOrder / verifyNotify），因此业务侧调用方式与 kode/pays 网关契约
 * **完全一致**、无需关心底层适配——这是本包统一命名的核心目标。
 *
 * 设计要点：
 *  - **硬依赖（唯一支付路径）**：2.0 起 kode/pays 为本包唯一支付实现。适配器在首次调用时探测
 *    `Kode\Pays\Facade\Pay` 是否存在；未安装时抛清晰异常，引导业务侧先 `composer require kode/pays`。
 *  - 凭证来源由外部注入的 {@see $configResolver} 提供（闭包），本类不耦合 miniapp 的配置结构，
 *    便于业务侧按 kode/pays 真实要求的字段自行拼装 config。
 *  - 微信商户 v2 密钥在 miniapp 中字段名为 `key`，需映射为 kode/pays 的 `api_key`，
 *    见 {@see PaysBridge::kernelResolver()} 的默认实现。
 *  - 付款人注入：当传入 {@see UnionUser} 时，自动取其 openid 写入对应渠道的原生付款人字段
 *    （微信 / QQ 为 `openid`、支付宝为 `buyer_id`）；`$order` 中已显式提供的字段不会被覆盖。
 */
final class PaysBridgePayAdapter implements AdvancedPayAdapter
{
    /**
     * kode/pays 门面类名（用变量拼接避免 PHPStan 在类未安装时报「类不存在」）
     */
    private const PAYS_FACADE = 'Kode\\Pays\\Facade\\Pay';

    /**
     * @param \Closure(Channel):array<string, mixed> $configResolver 返回 kode/pays 网关 config 数组
     */
    public function __construct(
        private readonly Channel $channel,
        private readonly \Closure $configResolver,
    ) {
    }

    #[\Override]
    public function channel(): Channel
    {
        return $this->channel;
    }

    /**
     * 统一下单（委托 kode/pays，并自动注入付款人身份）
     *
     * @param array<string, mixed> $order
     * @param UnionUser|null       $user 可选，已登录用户；其 openid 会被翻译为对应渠道的原生
     *                                    付款人字段（微信/QQ=openid、支付宝=buyer_id）后注入 $order。
     *                                    传 null 时业务侧须自行在 $order 中提供付款人标识。
     * @return array<string, mixed>
     */
    #[\Override]
    public function createOrder(array $order, ?UnionUser $user = null): array
    {
        // 桥接核心：把 miniapp 登录得到的付款人身份翻译为 kode/pays 的原生付款人字段。
        // kode/pays 的 createOrder(array $params) 不接收付款人对象，故由本桥接注入。
        $order = $this->injectPayer($order, $user);

        /** @var mixed $gateway */
        $gateway = $this->paysGateway();

        /** @var array<string, mixed> $result */
        $result = PaysBridge::invokeGateway(fn () => $gateway->createOrder($order), $this->channel, '下单');

        return $result;
    }

    /**
     * 查询订单（委托 kode/pays queryOrder）
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function queryOrder(string $orderId): array
    {
        /** @var mixed $gateway */
        $gateway = $this->paysGateway();

        /** @var array<string, mixed> $result */
        $result = PaysBridge::invokeGateway(fn () => $gateway->queryOrder($orderId), $this->channel, '查单');

        return $result;
    }

    #[\Override]
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function refund(array $params): array
    {
        /** @var mixed $gateway */
        $gateway = $this->paysGateway();

        /** @var array<string, mixed> $result */
        $result = PaysBridge::invokeGateway(fn () => $gateway->refund($params), $this->channel, '退款');

        return $result;
    }

    #[\Override]
    /** @return array<string, mixed> */
    public function queryRefund(string $refundId): array
    {
        /** @var mixed $gateway */
        $gateway = $this->paysGateway();

        /** @var array<string, mixed> $result */
        $result = PaysBridge::invokeGateway(fn () => $gateway->queryRefund($refundId), $this->channel, '退款查询');

        return $result;
    }

    #[\Override]
    /** @return array<string, mixed> */
    public function closeOrder(string $orderId): array
    {
        /** @var mixed $gateway */
        $gateway = $this->paysGateway();

        /** @var array<string, mixed> $result */
        $result = PaysBridge::invokeGateway(fn () => $gateway->closeOrder($orderId), $this->channel, '关单');

        return $result;
    }

    /**
     * 回调验签 + 解密（委托 kode/pays）
     *
     * kode/pays 的 `verifyNotify` 仅返回 bool（验签是否通过）。本方法补全为「验签通过则
     * 返回解析后的业务数据数组」，与 kode/pays verifyNotify 语义一致——业务侧统一按数组取
     * out_trade_no / transaction_id。
     *
     * 微信 V3 通知的 resource 为密文，桥接必须走 `WechatPayV3Gateway::decryptResource`
     * （AES-256-GCM 认证解密，依赖 api_v3_key）还原明文业务数据。注意：桥接默认把 Wechat*
     * 解析到 V2 网关 `WechatPayGateway`（无 `decryptResource`），故此处**显式**取
     * `wechat_v3` 网关实例处理 V3 通知——否则 V3 解密分支永远不触发（历史死代码）。
     *
     * V3 通知的 RSA 验签（Wechatpay-Signature + 平台证书）依赖原始请求体与证书信任链，
     * 由 HTTP 边界（持有 raw body + 头）负责；本方法聚焦「解密」并复用 GCM 认证保证密文完整性。
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    #[\Override]
    public function verifyNotify(array $payload, array $headers = []): array
    {
        // 微信 V3 通知：resource 为密文，必须走 V3 网关解密后再返回明文业务数据。
        if (isset($payload['resource']['ciphertext'])) {
            /** @var mixed $v3 */
            $v3 = $this->v3Gateway();
            if (!is_object($v3) || !method_exists($v3, 'decryptResource')) {
                throw new \RuntimeException(
                    "渠道 [{$this->channel->label()}] 不支持 V3 通知解密（缺少 WechatPayV3Gateway 或 api_v3_key 配置）",
                );
            }

            /** @var callable(array<string, mixed>):array<string, mixed> $fn */
            $fn = [$v3, 'decryptResource'];

            /** @var array<string, mixed> $decrypted */
            $decrypted = PaysBridge::invokeGateway(
                fn () => $fn($payload['resource']),
                $this->channel,
                'V3 回调解密',
            );

            return $decrypted;
        }

        /** @var mixed $gateway */
        $gateway = $this->paysGateway();

        /** @var bool $ok */
        $ok = PaysBridge::invokeGateway(fn () => $gateway->verifyNotify($payload), $this->channel, '回调验签');
        if (!$ok) {
            throw new \RuntimeException(
                "支付回调验签失败（渠道 [{$this->channel->label()}]）：请检查 APIv3 密钥 / 公钥配置",
            );
        }

        return $payload;
    }

    /**
     * 验证 Webhook 原始通知（微信 V3 验签 + 解密）
     *
     * 与 {@see verifyNotify()}（接收已解析数组、用于 V2 XML/JSON 通知）互补：本方法接收
     * **原始报文字符串**与 HTTP 头，先走 `WechatPayV3Gateway::verifyWebhook` 做 RSA-SHA256
     * 签名验证（Wechatpay-Signature / Timestamp / Nonce / Serial，依赖平台证书），验签通过
     * 后再对 `resource`（AES-256-GCM 密文）做解密，返回可信业务数组。验签失败抛
     * RuntimeException（无静默通过），与 verifyNotify 风格一致。
     *
     * 仅微信渠道适用（V3 Webhook 验签是微信特有）；其他渠道验签仍走 {@see verifyNotify()}。
     * 平台证书通过 config `platform_certificate`（PEM 公钥）注入；未配置则验签必失败。
     *
     * @param string $payload 原始请求体（JSON 字符串）
     * @param array<string, string> $headers 平台 HTTP 头
     * @return array<string, mixed>
     */
    #[\Override]
    public function verifyWebhook(string $payload, array $headers = []): array
    {
        // 仅微信渠道适用 V3 Webhook 验签（其他渠道验签走 verifyNotify）
        if (!in_array(self::gatewayMethod($this->channel), ['wechat', 'wechatWork'], true)) {
            throw new \InvalidArgumentException(
                "verifyWebhook 仅支持微信渠道（收到 [{$this->channel->label()}]），其他渠道请使用 verifyNotify",
            );
        }

        /** @var mixed $v3 */
        $v3 = $this->v3Gateway();
        if (!is_object($v3) || !method_exists($v3, 'verifyWebhook')) {
            throw new \RuntimeException(
                "渠道 [{$this->channel->label()}] 不支持 V3 通知验签（缺少 WechatPayV3Gateway）",
            );
        }

        // 平台证书是 V3 验签的前提：缺失时提前给出清晰报错，避免网关内部 openssl 警告噪声。
        /** @var array<string, mixed> $certConfig */
        $certConfig = ($this->configResolver)($this->channel);
        $cert = $certConfig['platform_certificate'] ?? null;
        if (!is_string($cert) || $cert === '') {
            throw new \RuntimeException(
                "微信 V3 支付回调验签失败（渠道 [{$this->channel->label()}]）：缺少平台证书（config platform_certificate）",
            );
        }

        /** @var callable(string, array<string, string>):bool $verify */
        $verify = [$v3, 'verifyWebhook'];

        /** @var bool $ok */
        $ok = PaysBridge::invokeGateway(
            fn () => $verify($payload, $headers),
            $this->channel,
            'V3 回调验签',
        );
        if (!$ok) {
            throw new \RuntimeException(
                "微信 V3 支付回调验签失败（渠道 [{$this->channel->label()}]）：请检查平台证书 / Wechatpay-* 头",
            );
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            throw new \RuntimeException('微信 V3 回调报文非合法 JSON');
        }

        if (isset($data['resource']) && is_array($data['resource']) && method_exists($v3, 'decryptResource')) {
            /** @var callable(array<string, mixed>):array<string, mixed> $dec */
            $dec = [$v3, 'decryptResource'];

            /** @var array<string, mixed> $decrypted */
            $decrypted = PaysBridge::invokeGateway(
                fn () => $dec($data['resource']),
                $this->channel,
                'V3 回调解密',
            );

            return $decrypted;
        }

        return $data;
    }

    /**
     * 取微信 V3 网关实例（用于 V3 通知解密）。
     *
     * 桥接默认把 Wechat* 解析到 V2 网关（WechatPayGateway），但其不含 `decryptResource`，
     * 故 V3 通知必须显式构造 `wechat_v3` 网关实例（共享同一份 config resolver 凭证）。
     *
     * @return mixed
     */
    private function v3Gateway(): mixed
    {
        $facade = self::PAYS_FACADE;
        if (!class_exists($facade) || !class_exists(GatewayFactory::class)) {
            throw new \RuntimeException(
                '支付能力已迁移至 kode/pays，请先执行 `composer require kode/pays` 后再调用 Union::notify()',
            );
        }

        /** @var array<string, mixed> $config */
        $config = ($this->configResolver)($this->channel);

        // 构造 V3 网关需要 mch_id / serial_no / private_key / api_key（与 V2 网关配置不同）。
        // 缺字段时 GatewayFactory 抛 PayException，归一为 ApiException（无静默失败）。
        /** @var mixed $gateway */
        $gateway = PaysBridge::invokeGateway(
            fn () => GatewayFactory::create('wechat_v3', $config),
            $this->channel,
            'V3 网关构造',
        );

        return $gateway;
    }

    /**
     * 发起分账（委托 kode/pays 网关 createProfitSharing）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function profitSharingCreate(array $params): array
    {
        return $this->callGatewayFeature('createProfitSharing', '分账', $params);
    }

    /**
     * 查询分账结果（委托 kode/pays 网关 queryProfitSharing）
     *
     * @param string      $outOrderNo
     * @param string|null $transactionId
     * @return array<string, mixed>
     */
    #[\Override]
    public function profitSharingQuery(string $outOrderNo, ?string $transactionId = null): array
    {
        return $this->callGatewayFeature('queryProfitSharing', '分账', $outOrderNo, $transactionId);
    }

    /**
     * 分账回退（委托 kode/pays 网关 returnProfitSharing）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function profitSharingReturn(array $params): array
    {
        return $this->callGatewayFeature('returnProfitSharing', '分账', $params);
    }

    /**
     * 查询分账回退结果（委托 kode/pays 网关 queryProfitSharingReturn）
     *
     * @param string $outReturnNo
     * @return array<string, mixed>
     */
    #[\Override]
    public function profitSharingQueryReturn(string $outReturnNo): array
    {
        return $this->callGatewayFeature('queryProfitSharingReturn', '分账', $outReturnNo);
    }

    /**
     * 解冻未分账的剩余资金（委托 kode/pays 网关 unfreezeProfitSharing）
     *
     * @param string      $transactionId
     * @param string|null $outOrderNo
     * @return array<string, mixed>
     */
    #[\Override]
    public function profitSharingUnfreeze(string $transactionId, ?string $outOrderNo = null): array
    {
        return $this->callGatewayFeature('unfreezeProfitSharing', '分账', $transactionId, $outOrderNo);
    }

    /**
     * 发起单笔转账 / 企业付款到零钱（委托 kode/pays 网关 singleTransfer）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function transferSingle(array $params): array
    {
        return $this->callGatewayFeature('singleTransfer', '转账', $params);
    }

    /**
     * 发起批量转账（委托 kode/pays 网关 batchTransfer）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function transferBatch(array $params): array
    {
        return $this->callGatewayFeature('batchTransfer', '转账', $params);
    }

    /**
     * 查询转账结果（委托 kode/pays 网关 queryTransfer）
     *
     * @param string $outBizNo
     * @return array<string, mixed>
     */
    #[\Override]
    public function transferQuery(string $outBizNo): array
    {
        return $this->callGatewayFeature('queryTransfer', '转账', $outBizNo);
    }

    /**
     * 查询转账电子回单（委托 kode/pays 网关 transferReceipt）
     *
     * @param string $outBizNo
     * @return array<string, mixed>
     */
    #[\Override]
    public function transferReceipt(string $outBizNo): array
    {
        return $this->callGatewayFeature('transferReceipt', '转账', $outBizNo);
    }

    /**
     * 下载交易对账单（委托 kode/pays 网关 downloadBill）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function reconciliationDownloadBill(array $params): array
    {
        return $this->callGatewayFeature('downloadBill', '对账', $params);
    }

    /**
     * 下载资金账单（委托 kode/pays 网关 downloadFundFlow）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function reconciliationDownloadFundFlow(array $params): array
    {
        return $this->callGatewayFeature('downloadFundFlow', '对账', $params);
    }

    /**
     * 解析对账单原始数据（委托 kode/pays 网关 parseBill）
     *
     * @param string $rawData
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    public function reconciliationParseBill(string $rawData): array
    {
        /** @var array<int, array<string, mixed>> $result */
        $result = $this->callGatewayFeature('parseBill', '对账', $rawData);

        return $result;
    }

    /**
     * 发放普通红包（委托 kode/pays 网关 sendRedPacket）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function redPacketSend(array $params): array
    {
        return $this->callGatewayFeature('sendRedPacket', '红包', $params);
    }

    /**
     * 发放裂变红包 / 群红包（委托 kode/pays 网关 groupRedPacket）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function redPacketGroup(array $params): array
    {
        return $this->callGatewayFeature('groupRedPacket', '红包', $params);
    }

    /**
     * 查询红包发放记录（委托 kode/pays 网关 queryRedPacket）
     *
     * @param string $mchBillNo
     * @return array<string, mixed>
     */
    #[\Override]
    public function redPacketQuery(string $mchBillNo): array
    {
        return $this->callGatewayFeature('queryRedPacket', '红包', $mchBillNo);
    }

    /**
     * 创建订阅计划（委托 kode/pays 网关 createPlan）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function subscriptionCreatePlan(array $params): array
    {
        return $this->callGatewayFeature('createPlan', '订阅', $params);
    }

    /**
     * 发起订阅（签约并首次扣款，委托 kode/pays 网关 createSubscription）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function subscriptionSubscribe(array $params): array
    {
        return $this->callGatewayFeature('createSubscription', '订阅', $params);
    }

    /**
     * 取消订阅（委托 kode/pays 网关 cancelSubscription）
     *
     * @param string $subscriptionId
     * @return array<string, mixed>
     */
    #[\Override]
    public function subscriptionCancel(string $subscriptionId): array
    {
        return $this->callGatewayFeature('cancelSubscription', '订阅', $subscriptionId);
    }

    /**
     * 暂停订阅（委托 kode/pays 网关 pauseSubscription）
     *
     * @param string $subscriptionId
     * @return array<string, mixed>
     */
    #[\Override]
    public function subscriptionPause(string $subscriptionId): array
    {
        return $this->callGatewayFeature('pauseSubscription', '订阅', $subscriptionId);
    }

    /**
     * 恢复订阅（委托 kode/pays 网关 resumeSubscription）
     *
     * @param string $subscriptionId
     * @return array<string, mixed>
     */
    #[\Override]
    public function subscriptionResume(string $subscriptionId): array
    {
        return $this->callGatewayFeature('resumeSubscription', '订阅', $subscriptionId);
    }

    /**
     * 查询订阅详情（委托 kode/pays 网关 getSubscription）
     *
     * @param string $subscriptionId
     * @return array<string, mixed>
     */
    #[\Override]
    public function subscriptionGet(string $subscriptionId): array
    {
        return $this->callGatewayFeature('getSubscription', '订阅', $subscriptionId);
    }

    /**
     * 查询账户余额（委托 kode/pays 网关 queryBalance）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function balanceQuery(array $params = []): array
    {
        return $this->callGatewayFeature('queryBalance', '余额', $params);
    }

    /**
     * 查询日终余额（委托 kode/pays 网关 queryDayEndBalance）
     *
     * @param string               $date
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function balanceQueryDayEnd(string $date, array $params = []): array
    {
        return $this->callGatewayFeature('queryDayEndBalance', '余额', $date, $params);
    }

    /**
     * 结算到钱包（委托 kode/pays 网关 settleToWallet）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function settlementToWallet(array $params): array
    {
        return $this->callGatewayFeature('settleToWallet', '结算', $params);
    }

    /**
     * 结算到银行卡（委托 kode/pays 网关 settleToBankCard）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function settlementToBankCard(array $params): array
    {
        return $this->callGatewayFeature('settleToBankCard', '结算', $params);
    }

    /**
     * 结算到代付（委托 kode/pays 网关 settleToPayout）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function settlementToPayout(array $params): array
    {
        return $this->callGatewayFeature('settleToPayout', '结算', $params);
    }

    /**
     * 查询结算单（委托 kode/pays 网关 querySettlement）
     *
     * @param string $outBizNo
     * @return array<string, mixed>
     */
    #[\Override]
    public function settlementQuery(string $outBizNo): array
    {
        return $this->callGatewayFeature('querySettlement', '结算', $outBizNo);
    }

    // ===== 个人收款（PersonalReceiveCapableInterface） =====

    #[\Override]
    public function personalReceiveCreateQrCode(array $params): array
    {
        return $this->callGatewayFeature('createQrCode', '个人收款', $params);
    }

    #[\Override]
    public function personalReceiveQueryRecords(array $params): array
    {
        return $this->callGatewayFeature('queryRecords', '个人收款', $params);
    }

    #[\Override]
    public function personalReceiveWithdraw(array $params): array
    {
        return $this->callGatewayFeature('withdraw', '个人收款', $params);
    }

    #[\Override]
    public function personalReceiveQueryWithdraw(string $outBizNo): array
    {
        return $this->callGatewayFeature('queryWithdraw', '个人收款', $outBizNo);
    }

    /**
     * 当前渠道是否支持「个人收款」能力（无需完整支付配置即可判断）
     */
    #[\Override]
    public function supportsPersonalReceive(): bool
    {
        return $this->gatewaySupports('createQrCode');
    }

    /**
     * 当前渠道是否支持「分账」能力（无需完整支付配置即可判断）
     */
    #[\Override]
    public function supportsProfitSharing(): bool
    {
        return $this->gatewaySupports('createProfitSharing');
    }

    /**
     * 当前渠道是否支持「转账」能力（无需完整支付配置即可判断）
     */
    #[\Override]
    public function supportsTransfer(): bool
    {
        return $this->gatewaySupports('singleTransfer');
    }

    /**
     * 当前渠道是否支持「对账」能力（无需完整支付配置即可判断）
     */
    #[\Override]
    public function supportsReconciliation(): bool
    {
        return $this->gatewaySupports('downloadBill');
    }

    /**
     * 当前渠道是否支持「红包」能力（无需完整支付配置即可判断）
     */
    #[\Override]
    public function supportsRedPacket(): bool
    {
        return $this->gatewaySupports('sendRedPacket');
    }

    /**
     * 当前渠道是否支持「订阅」能力（无需完整支付配置即可判断）
     */
    #[\Override]
    public function supportsSubscription(): bool
    {
        return $this->gatewaySupports('createSubscription');
    }

    /**
     * 当前渠道是否支持「余额」能力（无需完整支付配置即可判断）
     *
     * 注意：微信 V2 网关（WechatMini 等）不实现 BalanceCapableInterface，故返回 false。
     */
    #[\Override]
    public function supportsBalance(): bool
    {
        return $this->gatewaySupports('queryBalance');
    }

    /**
     * 当前渠道是否支持「结算」能力（无需完整支付配置即可判断）
     */
    #[\Override]
    public function supportsSettlement(): bool
    {
        return $this->gatewaySupports('settleToWallet');
    }

    /**
     * 当前渠道是否支持「Webhook 事件」能力（无需完整支付配置即可判断）
     */
    #[\Override]
    public function supportsWebhook(): bool
    {
        return $this->gatewaySupports('verifyWebhook');
    }

    /**
     * 当前渠道是否支持「退款」能力（申请 / 查询 / 取消退款）
     *
     * 基于 kode/pays 网关类是否实现 RefundCapableInterface（applyRefund）判断，
     * 无需完整支付配置即可调用。注意 cancelRefund 仅部分网关支持（如 Stripe），
     * 以 applyRefund 作为能力基线，返回 false 时退款适配器方法会抛清晰异常。
     */
    #[\Override]
    public function supportsRefund(): bool
    {
        return $this->gatewaySupports('applyRefund');
    }

    #[\Override]
    public function paymentCapabilities(): array
    {
        return [
            'profit_sharing'      => $this->supportsProfitSharing(),
            'transfer'            => $this->supportsTransfer(),
            'reconciliation'      => $this->supportsReconciliation(),
            'red_packet'          => $this->supportsRedPacket(),
            'subscription'        => $this->supportsSubscription(),
            'balance'             => $this->supportsBalance(),
            'settlement'          => $this->supportsSettlement(),
            'personal_receive'    => $this->supportsPersonalReceive(),
            'webhook'             => $this->supportsWebhook(),
            'refund'              => $this->supportsRefund(),
        ];
    }

    /**
     * 取得当前渠道的 kode/pays 网关实例（供 Webhook 适配器等兄弟适配器委托）
     *
     * @return mixed kode/pays 网关实例
     */
    public function gateway(): mixed
    {
        return $this->paysGateway();
    }

    /**
     * 委托真实 kode/pays 网关的「特色方法」（分账 / 转账 / 对账 / ...）
     *
     * 以 `method_exists` 守卫：仅当当前渠道的网关真正实现了该方法时才转发，
     * 否则抛清晰异常（如百度 / 企业微信网关未实现、或某平台暂未开通相关能力），
     * 避免「Call to undefined method」这类难以定位的致命错误。
     *
     * @param string       $method      网关原生方法名（如 createProfitSharing）
     * @param string       $capability  能力中文名（用于异常提示，如「分账」）
     * @param mixed        ...$args     透传给网关方法的参数
     * @return array<string, mixed>
     */
    private function callGatewayFeature(string $method, string $capability, mixed ...$args): array
    {
        /** @var mixed $gateway */
        $gateway = $this->paysGateway();

        if (!is_object($gateway) || !method_exists($gateway, $method)) {
            throw new \RuntimeException(
                "渠道 [{$this->channel->label()}] 的支付网关不支持 [{$capability}] 能力（未实现 {$method}）",
            );
        }

        /** @var callable(mixed...):array<string, mixed> $fn */
        $fn = [$gateway, $method];

        /** @var array<string, mixed> $result */
        $result = PaysBridge::invokeGateway(fn () => $fn(...$args), $this->channel, $capability);

        return $result;
    }

    /**
     * 判断当前渠道的 kode/pays 网关类是否实现了某特色方法（能力发现）
     *
     * 直接查 kode/pays 网关注册表拿到类名的「类级」method_exists，**无需构造实例、无需完整支付配置**，
     * 因此可在调用真实能力方法之前优雅判断（例如抖音仅支持分账、QQ 均不支持、百度未在注册表）。
     *
     * @param string $method 网关特色方法名（如 createProfitSharing）
     */
    private function gatewaySupports(string $method): bool
    {
        $facade = self::PAYS_FACADE;
        if (!class_exists($facade) || !class_exists(GatewayFactory::class)) {
            return false;
        }

        /** @var class-string|null $gatewayClass */
        $gatewayClass = GatewayFactory::getGatewayClass(self::gatewayMethod($this->channel));

        return $gatewayClass !== null && method_exists($gatewayClass, $method);
    }

    /**
     * 构造 kode/pays 网关（首次调用时探测 pays 是否已安装）
     *
     * @return mixed kode/pays 网关实例（类型在 pays 未安装时不可知，调用方已用 mixed 断言）
     */
    private function paysGateway(): mixed
    {
        $facade = self::PAYS_FACADE;
        if (!class_exists($facade)) {
            throw new \RuntimeException(
                '支付能力已迁移至 kode/pays，请先执行 `composer require kode/pays` 后再调用 Union::pay()',
            );
        }

        /** @var array<string, mixed> $config */
        $config = ($this->configResolver)($this->channel);

        $method = self::gatewayMethod($this->channel);

        /** @var mixed $gateway */
        $gateway = $facade::$method($config);

        return $gateway;
    }

    /**
     * 把已登录用户的付款人标识翻译并注入订单参数
     *
     * 仅当 $order 中尚未显式提供该付款人字段时才写入，不覆盖业务侧手动传入的值。
     * 同时通过渠道守卫确保付款人来自与本次支付「同一平台」，避免把 A 平台的 openid 付到 B 平台。
     *
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function injectPayer(array $order, ?UnionUser $user): array
    {
        if ($user === null) {
            return $order;
        }

        // 渠道守卫：付款人身份必须来自与本次支付同一平台（跨平台 openid 不互通）。
        if (self::gatewayMethod($user->channel) !== self::gatewayMethod($this->channel)) {
            throw new \InvalidArgumentException(
                "付款人身份渠道 [{$user->channel->label()}] 与支付渠道 [{$this->channel->label()}] "
                . '不属于同一平台，无法注入付款人标识（跨平台 openid 不互通）',
            );
        }

        $key = self::payerKey($this->channel);
        if ($key !== null && !array_key_exists($key, $order)) {
            $order[$key] = $user->openId;
        }

        return $order;
    }

    /**
     * 渠道 → kode/pays 原生付款人字段名
     */
    private static function payerKey(Channel $channel): ?string
    {
        return match ($channel) {
            Channel::WechatMp, Channel::WechatMini, Channel::WechatH5,
            Channel::WechatPc, Channel::WechatApp, Channel::WechatOpen, Channel::WechatWork,
            Channel::Qq => 'openid',
            Channel::AlipayMini, Channel::AlipayMp, Channel::AlipayApp => 'buyer_id',
            default => null,
        };
    }

    /**
     * 渠道 → kode/pays 网关静态方法名
     */
    private static function gatewayMethod(Channel $channel): string
    {
        return match ($channel) {
            Channel::WechatMp, Channel::WechatMini, Channel::WechatH5,
            Channel::WechatPc, Channel::WechatApp, Channel::WechatOpen => 'wechat',
            Channel::WechatWork => 'wechatWork',
            Channel::AlipayMini, Channel::AlipayMp, Channel::AlipayApp => 'alipay',
            Channel::DouyinMini, Channel::DouyinMp => 'douyin',
            Channel::Qq => 'qq',
            Channel::BaiduMini => 'baidu',
            Channel::Crypto => 'coinbase',
            default => throw new \InvalidArgumentException(
                "kode/pays 桥接暂不支持渠道 [{$channel->label()}]",
            ),
        };
    }
}
