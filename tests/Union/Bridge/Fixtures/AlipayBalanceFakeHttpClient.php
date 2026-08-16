<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Support\HttpClient;

/**
 * 支付宝余额查询假 HTTP 客户端（仅供测试）
 *
 * 继承真实 {@see HttpClient} 以通过 {@see \Kode\Pays\Facade\Pay::setHttpClient()} 的类型约束，
 * 拦截 AlipayGateway 发出的 POST 表单请求（余额查询走 {@see HttpClient::post}），
 * 返回带 `alipay_fund_account_query_response` 包裹的成功 JSON，使余额查询能走通
 * 真实网关「签名 / 报文拼装 / 响应解析」路径而不触网。
 *
 * 同时记录最近一次请求数据（含 method / sign / biz_content），供断言签名与业务参数。
 */
final class AlipayBalanceFakeHttpClient extends HttpClient
{
    public ?string $lastMethod = null;

    public ?string $lastUrl = null;

    /** @var array<string, mixed>|null 记录 post 的请求数据（含 sign / method / biz_content） */
    public ?array $lastData = null;

    /** @var array<string, string>|null 记录请求头 */
    public ?array $lastHeaders = null;

    public function post(string $url, array $data = [], array $headers = []): string
    {
        $this->lastMethod  = 'POST';
        $this->lastUrl     = $url;
        $this->lastData    = $data;
        $this->lastHeaders = $headers;

        return (string) json_encode([
            'alipay_fund_account_query_response' => [
                'code'              => '10000',
                'msg'               => 'Success',
                'available_amount'  => '100.00',
                'freeze_amount'     => '10.00',
                'total_amount'      => '110.00',
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
