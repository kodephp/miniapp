<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Support\HttpClient;
use Kode\Pays\Support\Signer;

/**
 * kode/pays 假 HTTP 客户端（仅供测试）
 *
 * 继承真实 {@see HttpClient} 以通过 {@see \Kode\Pays\Facade\Pay::setHttpClient()} 的类型约束，
 * 拦截网关发出的真实网络请求，按 URL 返回各支付网关「成功响应」的合法报文（已正确签名），
 * 使桥接测试能走通真实网关代码路径（参数校验 / 签名 / 报文拼装 / 响应解析）而不触网。
 *
 * 同时记录最近一次请求（方法 / URL / 请求体），供断言「付款人身份是否被正确注入并发送」。
 */
final class FakePaysHttpClient extends HttpClient
{
    public ?string $lastMethod = null;

    public ?string $lastUrl = null;

    /** @var string|null 记录 postRaw 的原始请求体（微信 V2 为 XML、QQ V3 为 JSON） */
    public ?string $lastRawBody = null;

    /** @var array<string, mixed>|null 记录 post/get/put/delete 的请求数据 */
    public ?array $lastData = null;

    /** @var array<string, mixed>|null 记录 get/delete 的查询参数 */
    public ?array $lastQuery = null;

    /** @var array<string, string>|null 记录请求头 */
    public ?array $lastHeaders = null;

    /**
     * @param string $apiKey 微信 V2 响应用于验签的 api_key（须与测试里传给网关的 config.api_key 一致）
     */
    public function __construct(private string $apiKey = 'unit_test_api_key_0123456789')
    {
        parent::__construct();
    }

    public function postRaw(string $url, string $body, array $headers = [], array $options = []): string
    {
        $this->lastMethod  = 'POST_RAW';
        $this->lastUrl     = $url;
        $this->lastRawBody = $body;
        $this->lastHeaders = $headers;

        // QQ 支付走 V3（JSON 报文），其余（微信 V2）为 XML 报文
        if (str_contains($url, 'qpay.qq.com')) {
            return $this->qqSuccessJson();
        }

        return $this->wechatSuccessXml();
    }

    public function post(string $url, array $data = [], array $headers = []): string
    {
        $this->lastMethod  = 'POST';
        $this->lastUrl     = $url;
        $this->lastData    = $data;
        $this->lastHeaders = $headers;

        return $this->douyinSuccessJson();
    }

    public function get(string $url, array $query = [], array $headers = []): string
    {
        $this->lastMethod  = 'GET';
        $this->lastUrl     = $url;
        $this->lastQuery   = $query;
        $this->lastHeaders = $headers;

        return $this->genericSuccessJson();
    }

    public function put(string $url, array $data = [], array $headers = []): string
    {
        $this->lastMethod  = 'PUT';
        $this->lastUrl     = $url;
        $this->lastData    = $data;
        $this->lastHeaders = $headers;

        return $this->genericSuccessJson();
    }

    public function delete(string $url, array $query = [], array $headers = []): string
    {
        $this->lastMethod  = 'DELETE';
        $this->lastUrl     = $url;
        $this->lastQuery   = $query;
        $this->lastHeaders = $headers;

        return $this->genericSuccessJson();
    }

    /**
     * 微信 V2 成功响应（XML + MD5 签名，使用与网关相同的 api_key）
     */
    private function wechatSuccessXml(): string
    {
        $resp = [
            'return_code' => 'SUCCESS',
            'return_msg'  => 'OK',
            'result_code' => 'SUCCESS',
            'prepay_id'   => 'WXPREPAY_1',
            'nonce_str'   => 'unit_test_nonce',
        ];

        $resp['sign'] = Signer::md5($resp, $this->apiKey);

        $xml = '<xml>';
        foreach ($resp as $key => $val) {
            $xml .= is_numeric($val)
                ? "<{$key}>{$val}</{$key}>"
                : "<{$key}><![CDATA[{$val}]]></{$key}>";
        }
        $xml .= '</xml>';

        return $xml;
    }

    /**
     * QQ 支付成功响应（JSON，code=SUCCESS）
     */
    private function qqSuccessJson(): string
    {
        return (string) json_encode([
            'code'      => 'SUCCESS',
            'prepay_id' => 'QQPREPAY_1',
            'code_url'  => 'QQCODE_1',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 抖音支付成功响应（JSON，err_no=0）
     */
    private function douyinSuccessJson(): string
    {
        return (string) json_encode([
            'err_no'    => 0,
            'err_tips'  => 'success',
            'prepay_id' => 'DYPREPAY_1',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 通用成功响应（JSON）
     */
    private function genericSuccessJson(): string
    {
        return (string) json_encode(['code' => 'SUCCESS', 'return_code' => 'SUCCESS'], JSON_UNESCAPED_UNICODE);
    }
}
