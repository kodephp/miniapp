<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat;

use RuntimeException;

/**
 * 微信支付 V3 请求签名器
 *
 * 按微信支付 V3 规范为每个请求生成 Authorization 头：
 *
 *   Authorization: WECHATPAY2-SHA256-RSA2048 mchid="",nonce_str="",signature="",timestamp="",serial_no=""
 *
 * 待签名串（以 \n 结尾）：
 *
 *   method\n
 *   url(path+query，不含域名)\n
 *   timestamp\n
 *   noncestr\n
 *   body\n
 *
 * 使用商户私钥以 SHA256withRSA 签名后做 Base64 编码。缺少此头时微信商户平台会返回 401。
 */
final class V3Signer
{
    public function __construct(
        private readonly string $mchId,
        private readonly string $serialNo,
        private readonly string $privateKey,
    ) {
    }

    /**
     * 生成 Authorization 头值
     *
     * @param string $method HTTP 方法（GET/POST，建议大写）
     * @param string $path   请求路径（含 query，不含域名），如 /v3/pay/transactions/jsapi
     * @param string $body   请求体原文（GET 为空字符串）
     */
    public function authorization(string $method, string $path, string $body): string
    {
        $timestamp = (string) time();
        $nonce     = bin2hex(random_bytes(16));
        $message   = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
            $this->mchId,
            $nonce,
            $this->sign($message),
            $timestamp,
            $this->serialNo
        );
    }

    private function sign(string $message): string
    {
        $key = openssl_pkey_get_private($this->privateKey);
        if ($key === false) {
            throw new RuntimeException('微信支付商户私钥无效，请检查 key_path 配置');
        }

        $ok = openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);
        if ($ok === false || $signature === '') {
            throw new RuntimeException('微信支付 V3 签名计算失败');
        }

        return base64_encode($signature);
    }
}
