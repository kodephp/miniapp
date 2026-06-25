<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Providers\Wechat\WechatConfig;

/**
 * 微信红包模块
 */
readonly class Redpack
{
    private const BASE_URL = 'https://api.mch.weixin.qq.com/mmpaymkttransfers';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 发送普通红包
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function send(array $data): array
    {
        $config = $this->config();
        $data['wxappid']   = $config->appId();
        $data['mch_id']    = $config->mchId();
        $data['nonce_str'] = $this->generateNonce();

        $data['sign'] = $this->sign($data, $config->apiV3Key());

        $xml = $this->toXml($data);
        $response = $this->app->http()->post(
            self::BASE_URL . '/sendredpack',
            [
                'body'    => $xml,
                'headers' => ['Content-Type' => 'text/xml'],
            ]
        );

        return $this->fromXml((string) $response->getBody());
    }

    /**
     * 发送裂变红包
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendGroup(array $data): array
    {
        $config = $this->config();
        $data['wxappid']   = $config->appId();
        $data['mch_id']    = $config->mchId();
        $data['nonce_str'] = $this->generateNonce();
        $data['amt_type']  = 'ALL_RAND';

        $data['sign'] = $this->sign($data, $config->apiV3Key());

        $xml = $this->toXml($data);
        $response = $this->app->http()->post(
            self::BASE_URL . '/sendgroupredpack',
            [
                'body'    => $xml,
                'headers' => ['Content-Type' => 'text/xml'],
            ]
        );

        return $this->fromXml((string) $response->getBody());
    }

    /**
     * 查询红包记录
     *
     * @return array<string, mixed>
     */
    public function query(string $mchBillNo, string $billType = 'MCHT'): array
    {
        $config = $this->config();
        $data = [
            'nonce_str'  => $this->generateNonce(),
            'mch_billno' => $mchBillNo,
            'mch_id'     => $config->mchId(),
            'appid'      => $config->appId(),
            'bill_type'  => $billType,
        ];
        $data['sign'] = $this->sign($data, $config->apiV3Key());

        $xml = $this->toXml($data);
        $response = $this->app->http()->post(
            self::BASE_URL . '/gethbinfo',
            [
                'body'    => $xml,
                'headers' => ['Content-Type' => 'text/xml'],
            ]
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

    private function config(): WechatConfig
    {
        return $this->app->config();
    }
}
