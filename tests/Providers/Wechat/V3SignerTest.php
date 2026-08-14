<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Wechat;

use Kode\MiniApp\Providers\Wechat\V3Signer;
use PHPUnit\Framework\TestCase;

/**
 * 微信支付 V3 签名器单元测试
 *
 * 用本地生成的 RSA 密钥对验证签名算法的正确性与 Authorization 头格式。
 */
final class V3SignerTest extends TestCase
{
    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        \assert($res !== false);

        $ok = openssl_pkey_export($res, $out);
        \assert($ok === true);
        $this->privateKey = $out;

        $details = openssl_pkey_get_details($res);
        \assert(is_array($details) && isset($details['key']));
        $this->publicKey = $details['key'];
    }

    public function testAuthorizationHeaderFormat(): void
    {
        $signer = new V3Signer('mch_123', 'serial_abc', $this->privateKey);
        $auth   = $signer->authorization('POST', '/v3/pay/transactions/jsapi', '{"a":1}');

        self::assertStringStartsWith('WECHATPAY2-SHA256-RSA2048 ', $auth);

        $p = $this->parse($auth);
        self::assertSame('mch_123', $p['mchid']);
        self::assertSame('serial_abc', $p['serial_no']);
        self::assertMatchesRegularExpression('/^\d{10,}$/', $p['timestamp']);
        self::assertSame(32, strlen($p['nonce_str']));
        self::assertNotEmpty($p['signature']);
        self::assertNotFalse(base64_decode($p['signature'], true), 'signature 应为合法 Base64');
    }

    public function testSignatureVerifiesWithPublicKey(): void
    {
        $signer = new V3Signer('mch_123', 'serial_abc', $this->privateKey);
        $method = 'POST';
        $path   = '/v3/pay/transactions/jsapi';
        $body   = '{"a":1}';
        $auth   = $signer->authorization($method, $path, $body);
        $p      = $this->parse($auth);

        $message = $method . "\n" . $path . "\n" . $p['timestamp'] . "\n" . $p['nonce_str'] . "\n" . $body . "\n";
        $raw     = base64_decode($p['signature'], true);
        \assert(is_string($raw));
        $result  = openssl_verify($message, $raw, $this->publicKey, OPENSSL_ALGO_SHA256);

        self::assertSame(1, $result, 'V3 签名应能通过公钥验签');
    }

    public function testGetRequestSignsEmptyBody(): void
    {
        $signer = new V3Signer('mch_123', 'serial_abc', $this->privateKey);
        $path   = '/v3/pay/transactions/out-trade-no/ABC?mchid=mch_123';
        $auth   = $signer->authorization('GET', $path, '');
        $p      = $this->parse($auth);

        $message = 'GET' . "\n" . $path . "\n" . $p['timestamp'] . "\n" . $p['nonce_str'] . "\n" . "\n";
        $raw     = base64_decode($p['signature'], true);
        \assert(is_string($raw));
        $result  = openssl_verify($message, $raw, $this->publicKey, OPENSSL_ALGO_SHA256);

        self::assertSame(1, $result);
    }

    public function testSignatureIsDifferentEachCall(): void
    {
        $signer = new V3Signer('mch_123', 'serial_abc', $this->privateKey);
        $a = $signer->authorization('POST', '/v3/x', '{}');
        $b = $signer->authorization('POST', '/v3/x', '{}');

        self::assertNotSame($a, $b);
    }

    /**
     * @return array{mchid:string,nonce_str:string,signature:string,timestamp:string,serial_no:string}
     */
    private function parse(string $auth): array
    {
        self::assertMatchesRegularExpression('/^WECHATPAY2-SHA256-RSA2048 (.+)$/', $auth);
        preg_match_all('/(\w+)="([^"]*)"/', $auth, $mm, PREG_SET_ORDER);

        $pairs = [];
        foreach ($mm as $g) {
            $pairs[$g[1]] = $g[2];
        }

        return [
            'mchid'     => $pairs['mchid'],
            'nonce_str' => $pairs['nonce_str'],
            'signature' => $pairs['signature'],
            'timestamp' => $pairs['timestamp'],
            'serial_no' => $pairs['serial_no'],
        ];
    }
}
