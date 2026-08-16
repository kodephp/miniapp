<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge\Fixtures;

use Kode\Pays\Support\HttpClient;
use Kode\Pays\Support\Signer;

/**
 * 仅对微信 V2 返回「通信成功但业务失败」的失败 XML 的假 HTTP 客户端。
 *
 * 用于验证 WechatPayGateway::parseResponse 在 result_code=FAIL 时把
 * err_code / err_code_des 透传为 PayException 的 gatewayCode / gatewayMessage，
 * 并最终落入 ApiException::payload。
 *
 * 直接继承 kode/pays 的 HttpClient（Pay::setHttpClient 接受该类型）；
 * 原 FakePaysHttpClient 为 final 不可继承，故此处独立实现最小失败分支。
 */
final class FailureXmlFakeHttpClient extends HttpClient
{
    public function __construct(private string $key = 'unit_test_api_key_0123456789')
    {
        parent::__construct();
    }

    public function postRaw(string $url, string $body, array $headers = [], array $options = []): string
    {
        $resp = [
            'return_code' => 'SUCCESS',
            'return_msg'  => 'OK',
            'result_code' => 'FAIL',
            'err_code'    => 'AMOUNT_LIMIT',
            'err_code_des' => '付款金额超限',
            'nonce_str'    => 'unit_test_nonce',
        ];
        $resp['sign'] = Signer::md5($resp, $this->key);

        $xml = '<xml>';
        foreach ($resp as $k => $v) {
            $xml .= is_numeric($v)
                ? "<{$k}>{$v}</{$k}>"
                : "<{$k}><![CDATA[{$v}]]></{$k}>";
        }

        return $xml . '</xml>';
    }
}
