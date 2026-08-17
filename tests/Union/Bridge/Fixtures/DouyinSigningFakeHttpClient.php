<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Support\HttpClient;

/**
 * 抖音支付 MD5+salt 签名链假 HTTP 客户端（仅供测试）
 *
 * 继承真实 {@see HttpClient} 以通过 {@see \Kode\Pays\Facade\Pay::setHttpClient()} 的类型约束，
 * 拦截 DouyinPayGateway 发出的 POST 请求（createOrder / queryOrder / refund / queryRefund /
 * createProfitSharing / queryProfitSharing 均走 post + application/json），返回带 err_no=0 的
 * 成功 JSON，使抖音网关能走通真实「MD5+salt 签名 / 报文拼装 / 响应解析」路径而不触网。
 *
 * 同时记录最近一次请求数据（含 sign / timestamp），供测试用同一 salt 独立重算 MD5 验签，
 * 证明真实网关的签名串可被外部复核（与微信 V2 用 Signer::verifyMd5 重算同属强证据）。
 */
final class DouyinSigningFakeHttpClient extends HttpClient
{
    public string $lastMethod = 'POST';

    public string $lastUrl = '';

    /** @var array<string, mixed>|null 记录 post 的请求数据（含 sign / timestamp） */
    public ?array $lastData = null;

    /** @var array<string, string> */
    public array $lastHeaders = [];

    public function post(string $url, array $data = [], array $headers = []): string
    {
        $this->lastMethod  = 'POST';
        $this->lastUrl     = $url;
        $this->lastData    = $data;
        $this->lastHeaders = $headers;

        // 抖音成功报文必须含 err_no=0（DouyinPayGateway::parseResponse 据此判定成功）
        return (string) json_encode([
            'err_no'        => 0,
            'err_tips'      => 'success',
            'order_id'      => (string) ($data['out_order_no'] ?? 'O123'),
            'out_order_no'  => $data['out_order_no'] ?? 'O123',
            'out_refund_no' => $data['out_refund_no'] ?? 'R123',
            'out_settle_no' => $data['out_settle_no'] ?? 'S123',
            'settle_no'     => 'SETTLE_001',
            'status'        => 'success',
        ], JSON_UNESCAPED_UNICODE);
    }
}
