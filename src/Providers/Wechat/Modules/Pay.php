<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\V3Signer;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * 微信支付模块（V3 版）
 *
 * 统一承载微信生态全部支付场景：
 *  - 交易类型：JSAPI（公众号 / 小程序）、APP（移动应用）、H5（MWEB）、NATIVE（PC 扫码）；
 *  - 商户模式：直连商户（普通商户号）与服务商（sp_mchid / sub_mchid）两种；
 *  - 所有请求均按微信支付 V3 规范自动附加 Authorization 签名头，否则商户平台返回 401。
 *
 * 所有端共用同一套 V3 签名器（SHA256-RSA2048），不再存在 V2 / V3 两套割裂机制。
 */
readonly class Pay
{
    private const BASE_URL = 'https://api.mch.weixin.qq.com/v3';

    /**
     * 交易类型 → 下单端点
     *
     * @var array<string, string>
     */
    private const TRADE_ENDPOINTS = [
        'JSAPI'  => '/pay/transactions/jsapi',
        'APP'    => '/pay/transactions/app',
        'MWEB'   => '/pay/transactions/h5',
        'NATIVE' => '/pay/transactions/native',
    ];

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 统一下单
     *
     * @param string               $tradeType 交易类型：JSAPI / APP / MWEB / NATIVE
     * @param array<string, mixed> $params    下单参数（可覆盖 appid / sub_appid 等）
     * @return array<string, mixed>
     */
    public function order(string $tradeType, array $params): array
    {
        if (!isset(self::TRADE_ENDPOINTS[$tradeType])) {
            throw new \InvalidArgumentException("不支持的微信支付交易类型：{$tradeType}");
        }

        $url      = self::BASE_URL . self::TRADE_ENDPOINTS[$tradeType];
        $body     = $this->json($this->buildPayload($params));
        $response = $this->signed('POST', $url, $body);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询订单
     *
     * @return array<string, mixed>
     */
    public function query(string $outTradeNo): array
    {
        $mchKey   = $this->isServiceProvider() ? 'sp_mchid' : 'mchid';
        $mchValue = $this->isServiceProvider()
            ? $this->app->config()->spMchId()
            : $this->app->config()->mchId();
        $url      = self::BASE_URL . "/pay/transactions/out-trade-no/{$outTradeNo}?{$mchKey}={$mchValue}";
        $response = $this->signed('GET', $url, '');

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 关闭订单
     *
     * @return array<string, mixed>
     */
    public function close(string $outTradeNo): array
    {
        $mchId    = $this->isServiceProvider()
            ? $this->app->config()->spMchId()
            : $this->app->config()->mchId();
        $url      = self::BASE_URL . "/pay/transactions/out-trade-no/{$outTradeNo}/close";
        $body     = $this->json(['mchid' => $mchId]);
        $response = $this->signed('POST', $url, $body);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 申请退款
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function refund(array $params): array
    {
        $url      = self::BASE_URL . '/refund/domestic/refunds';
        $body     = $this->json($params);
        $response = $this->signed('POST', $url, $body);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询退款
     *
     * @return array<string, mixed>
     */
    public function queryRefund(string $outRefundNo): array
    {
        $url      = self::BASE_URL . "/refund/domestic/refunds/{$outRefundNo}";
        $response = $this->signed('GET', $url, '');

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 申请交易账单
     *
     * @return array<string, mixed>
     */
    public function tradeBill(string $billDate, string $billType = 'ALL'): array
    {
        $url      = self::BASE_URL . "/bill/tradebill?bill_date={$billDate}&bill_type={$billType}";
        $response = $this->signed('GET', $url, '');

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 申请资金账单
     *
     * @return array<string, mixed>
     */
    public function fundBill(string $billDate, string $accountType = 'BASIC'): array
    {
        $url      = self::BASE_URL . "/bill/fundflowbill?bill_date={$billDate}&account_type={$accountType}";
        $response = $this->signed('GET', $url, '');

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 构造请求体
     *
     * 直连商户：appid / mchid；服务商：sp_mchid / sub_mchid / sub_appid。
     * 业务参数（含可覆盖的 appid / sub_appid）合并在后。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function buildPayload(array $params): array
    {
        $cfg = $this->app->config();

        if ($this->isServiceProvider()) {
            $base = [
                'sp_mchid'  => $cfg->spMchId(),
                'sub_mchid' => $cfg->subMchId(),
                'sub_appid' => $cfg->subAppId() !== '' ? $cfg->subAppId() : $cfg->appId(),
                'notify_url' => $cfg->get('notify_url', ''),
            ];
        } else {
            $base = [
                'appid'      => $cfg->appId(),
                'mchid'      => $cfg->mchId(),
                'notify_url' => $cfg->get('notify_url', ''),
            ];
        }

        return array_merge($base, $params);
    }

    /**
     * 以 V3 签名发送请求
     *
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('微信支付请求体编码失败');
        }

        return $body;
    }

    private function signed(string $method, string $url, string $body): ResponseInterface
    {
        $headers = [
            'Authorization' => $this->signer()->authorization(
                strtoupper($method),
                $this->pathForSign($url),
                $body
            ),
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($method === 'GET') {
            return $this->app->http()->get($url, ['headers' => $headers]);
        }

        return $this->app->http()->post($url, ['headers' => $headers, 'body' => $body]);
    }

    /**
     * 从完整 URL 中提取「路径 + query」作为签名串中的 url 部分
     */
    private function pathForSign(string $url): string
    {
        $parts = parse_url($url);
        $path  = (string) ($parts['path'] ?? '/');
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . (string) $parts['query'];
        }

        return $path;
    }

    private function signer(): V3Signer
    {
        $config  = $this->app->config();
        $keyPath = $config->keyPath();
        if ($keyPath === null || $keyPath === '') {
            throw new RuntimeException('微信支付 V3 签名失败：未配置商户私钥路径 key_path');
        }

        $privateKey = file_get_contents($keyPath);
        if ($privateKey === false) {
            throw new RuntimeException("微信支付 V3 签名失败：无法读取商户私钥 {$keyPath}");
        }

        $serialNo = $config->mchSerialNo();
        if ($serialNo === '') {
            throw new RuntimeException('微信支付 V3 签名失败：未配置证书序列号 mch_serial_no');
        }

        // 服务商模式：Authorization 头中的 mchid 必须是服务商商户号 sp_mchid
        $signMchId = $this->isServiceProvider() ? $config->spMchId() : $config->mchId();

        return new V3Signer($signMchId, $serialNo, $privateKey);
    }

    private function isServiceProvider(): bool
    {
        return $this->app->config()->isServiceProvider();
    }
}
