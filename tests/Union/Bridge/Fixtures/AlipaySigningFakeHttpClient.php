<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Support\HttpClient;

/**
 * 支付宝通用签名链假 HTTP 客户端（仅供测试）
 *
 * 继承真实 {@see HttpClient} 以通过 {@see \Kode\Pays\Facade\Pay::setHttpClient()} 的类型约束，
 * 拦截 AlipayGateway 发出的 POST 表单请求（分账 / 转账 / 红包 / 订阅等高级能力均走
 * {@see HttpClient::post}），返回带指定响应包裹的成功 JSON，使高级能力能走通真实网关
 * 「RSA2 签名 / 报文拼装 / 响应解析」路径而不触网。
 *
 * 同时记录最近一次请求数据（含 method / sign / biz_content），供断言签名与业务参数。
 */
final class AlipaySigningFakeHttpClient extends HttpClient
{
    public ?string $lastUrl = null;

    /** @var array<string, mixed>|null 记录 post 的请求数据（含 sign / method / biz_content） */
    public ?array $lastData = null;

    /** @var array<string, string>|null 记录请求头 */
    public ?array $lastHeaders = null;

    /**
     * @param string              $responseKey  支付宝成功响应体的首 key（如 alipay_trade_order_settle_response）
     * @param array<string, mixed> $responseBody 成功响应体的业务字段
     */
    public function __construct(
        private readonly string $responseKey,
        private readonly array $responseBody,
    ) {
    }

    public function post(string $url, array $data = [], array $headers = []): string
    {
        $this->lastUrl     = $url;
        $this->lastData    = $data;
        $this->lastHeaders = $headers;

        return (string) json_encode([
            $this->responseKey => $this->responseBody,
        ], JSON_UNESCAPED_UNICODE);
    }
}
