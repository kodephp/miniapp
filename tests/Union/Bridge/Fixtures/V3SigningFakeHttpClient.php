<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Support\HttpClient;

/**
 * 拦截微信 APIv3 请求（POST/GET）的测试客户端。
 *
 * 与 FakePaysHttpClient（V2 XML）不同，V3 端点返回 JSON 且响应体由调用方网关
 * 直返（WechatPayGateway 的 signedV3Post/signedV3Get 不经解密即返回 postRaw/get 的结果），
 * 因此本客户端返回合法 JSON 成功报文即可支撑「出站 V3 Authorization 签名链」断言。
 *
 * 用于证明真实 WechatPayGateway 在 transferBatch / transferQuery / transferReceipt
 * 三个 V3 方法上，确实以商户私钥（private_key）对
 * `METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY\n` 完成 RSA-SHA256 签名，并带正确 serial_no。
 */
final class V3SigningFakeHttpClient extends HttpClient
{
    public string $lastMethod = '';

    public string $lastUrl = '';

    public string $lastRawBody = '';

    /** @var array<string, string> */
    public array $lastHeaders = [];

    /** @var array<string, mixed> */
    public array $lastQuery = [];

    public function postRaw(string $url, string $body, array $headers = [], array $options = []): string
    {
        $this->lastMethod   = 'POST';
        $this->lastUrl      = $url;
        $this->lastRawBody  = $body;
        $this->lastHeaders  = $headers;
        $this->lastQuery    = [];

        return (string) json_encode([
            'out_batch_no'          => 'B20260816',
            'batch_id'              => 'BATCH_001',
            'create_time'           => '2026-08-16T09:00:00+08:00',
            'estimated_switch_time' => '2026-08-16T09:00:00+08:00',
        ], JSON_UNESCAPED_UNICODE);
    }

    public function get(string $url, array $query = [], array $headers = []): string
    {
        $this->lastMethod   = 'GET';
        $this->lastUrl      = $url;
        $this->lastQuery    = $query;
        $this->lastHeaders  = $headers;
        $this->lastRawBody  = '';

        return (string) json_encode([
            'out_batch_no' => 'B20260816',
            'batch_id'     => 'BATCH_001',
            'batch_status' => 'ACCEPTED',
        ], JSON_UNESCAPED_UNICODE);
    }
}
