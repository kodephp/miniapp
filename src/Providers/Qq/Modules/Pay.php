<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Qq\Modules;

use Kode\MiniApp\Providers\Qq\QqApp;

/**
 * QQ支付模块
 */
readonly class Pay
{
    private const BASE_URL = 'https://api.unipay.qq.com/v1/r';

    public function __construct(
        private QqApp $app,
    ) {
    }

    /**
     * 统一下单
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function unifiedOrder(array $data): array
    {
        $config = $this->app->config();
        $params = array_merge([
            'appid'     => $config->appId(),
            'mch_id'    => $config->get('mch_id', ''),
            'nonce_str' => $this->generateNonce(),
        ], $data);

        $params['sign'] = $this->sign($params, $config->get('api_key', ''));

        $xml      = $this->toXml($params);
        $response = $this->app->http()->post(
            self::BASE_URL . '/145/minipay/unifiedorder',
            ['body' => $xml, 'headers' => ['Content-Type' => 'text/xml']]
        );

        return $this->fromXml((string) $response->getBody());
    }

    /**
     * 查询订单
     *
     * @return array<string, mixed>
     */
    public function orderQuery(string $outTradeNo): array
    {
        $config = $this->app->config();
        $params = [
            'appid'        => $config->appId(),
            'mch_id'       => $config->get('mch_id', ''),
            'out_trade_no' => $outTradeNo,
            'nonce_str'    => $this->generateNonce(),
        ];
        $params['sign'] = $this->sign($params, $config->get('api_key', ''));

        $xml      = $this->toXml($params);
        $response = $this->app->http()->post(
            self::BASE_URL . '/145/minipay/orderquery',
            ['body' => $xml, 'headers' => ['Content-Type' => 'text/xml']]
        );

        return $this->fromXml((string) $response->getBody());
    }

    /**
     * 关闭订单
     *
     * @return array<string, mixed>
     */
    public function closeOrder(string $outTradeNo): array
    {
        $config = $this->app->config();
        $params = [
            'appid'        => $config->appId(),
            'mch_id'       => $config->get('mch_id', ''),
            'out_trade_no' => $outTradeNo,
            'nonce_str'    => $this->generateNonce(),
        ];
        $params['sign'] = $this->sign($params, $config->get('api_key', ''));

        $xml      = $this->toXml($params);
        $response = $this->app->http()->post(
            self::BASE_URL . '/145/minipay/closeorder',
            ['body' => $xml, 'headers' => ['Content-Type' => 'text/xml']]
        );

        return $this->fromXml((string) $response->getBody());
    }

    /**
     * 申请退款
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function refund(array $data): array
    {
        $config = $this->app->config();
        $params = array_merge([
            'appid'     => $config->appId(),
            'mch_id'    => $config->get('mch_id', ''),
            'nonce_str' => $this->generateNonce(),
        ], $data);

        $params['sign'] = $this->sign($params, $config->get('api_key', ''));

        $xml      = $this->toXml($params);
        $response = $this->app->http()->post(
            self::BASE_URL . '/145/minipay/refund',
            ['body' => $xml, 'headers' => ['Content-Type' => 'text/xml']]
        );

        return $this->fromXml((string) $response->getBody());
    }

    /**
     * 生成随机字符串
     */
    private function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * MD5签名
     *
     * @param array<string, mixed> $params
     */
    private function sign(array $params, string $key): string
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null && $k !== 'sign') {
                $string .= "{$k}={$v}&";
            }
        }
        $string .= "key={$key}";

        return strtoupper(md5($string));
    }

    /**
     * 数组转XML
     *
     * @param array<string, mixed> $data
     */
    private function toXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $val) {
            $xml .= is_numeric($val) ? "<{$key}>{$val}</{$key}>" : "<{$key}><![CDATA[{$val}]]></{$key}>";
        }
        $xml .= '</xml>';

        return $xml;
    }

    /**
     * XML转数组
     *
     * @return array<string, mixed>
     */
    private function fromXml(string $xml): array
    {
        $data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($data === false) {
            return [];
        }

        $json = json_encode($data);
        if ($json === false) {
            return [];
        }

        return json_decode($json, true) ?: [];
    }
}
