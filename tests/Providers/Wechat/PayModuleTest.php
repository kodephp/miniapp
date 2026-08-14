<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Wechat;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Tests\Fakes\CapturingHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * 微信支付模块集成测试
 *
 * 用本地临时私钥 + 捕获型 HttpClient 验证：每次 V3 请求都自动附加
 * 可被公钥验签的 Authorization 头，且返回体按预期解析。
 */
final class PayModuleTest extends TestCase
{
    private string $keyFile;
    private string $privateKey;
    private string $publicKey;
    private CapturingHttpClient $http;

    protected function setUp(): void
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        \assert($res !== false);
        openssl_pkey_export($res, $key);
        $this->privateKey = $key;
        $details = openssl_pkey_get_details($res);
        \assert(is_array($details) && isset($details['key']));
        $this->publicKey = $details['key'];

        $this->keyFile = tempnam(sys_get_temp_dir(), 'wxkey') . '.pem';
        file_put_contents($this->keyFile, $this->privateKey);

        $this->http = new CapturingHttpClient();
    }

    protected function tearDown(): void
    {
        if (is_file($this->keyFile)) {
            unlink($this->keyFile);
        }
    }

    private function app(): WechatApp
    {
        $app = (new Kernel([
            'wechat' => [
                'app_id'        => 'wxapp0000000000',
                'secret'        => 'app-secret',
                'mch_id'        => 'mch_123456',
                'mch_serial_no' => 'serial_no_xyz',
                'key_path'      => $this->keyFile,
                'notify_url'    => 'https://example.com/notify',
            ],
        ], $this->http))->wechat()->app();
        \assert($app instanceof WechatApp);

        return $app;
    }

    /**
     * 服务商模式（sp_mchid / sub_mchid）配置下的 App
     */
    private function spApp(): WechatApp
    {
        $app = (new Kernel([
            'wechat' => [
                'app_id'        => 'wxapp0000000000',
                'secret'        => 'app-secret',
                'sp_mchid'      => 'sp_mch_888',
                'sub_mchid'     => 'sub_mch_999',
                'sub_appid'     => 'wxsub_app_1',
                'mch_serial_no' => 'serial_no_xyz',
                'key_path'      => $this->keyFile,
                'notify_url'    => 'https://example.com/notify',
            ],
        ], $this->http))->wechat()->app();
        \assert($app instanceof WechatApp);

        return $app;
    }

    public function testOrderSendsSignedAuthorization(): void
    {
        $this->http->stub(['prepay_id' => 'prepay_abc']);
        $result = $this->app()->pay()->order('JSAPI', [
            'description' => 'test',
            'amount'      => ['total' => 1],
            'out_trade_no' => 'T1',
        ]);

        self::assertSame('prepay_abc', $result['prepay_id']);

        $req = $this->http->last();
        self::assertSame('POST', $req['method']);
        self::assertStringStartsWith('WECHATPAY2-SHA256-RSA2048 ', $req['headers']['Authorization']);
        $this->assertSignatureValid('POST', $req['uri'], $req['body'], $req['headers']['Authorization']);
    }

    public function testAppOrderUsesAppEndpoint(): void
    {
        $this->http->stub(['prepay_id' => 'prepay_app']);
        $this->app()->pay()->order('APP', [
            'description' => 'test',
            'amount'      => ['total' => 1],
            'out_trade_no' => 'T_APP',
        ]);

        $req = $this->http->last();
        self::assertSame('POST', $req['method']);
        self::assertStringEndsWith('/pay/transactions/app', $req['uri']);
        $this->assertSignatureValid('POST', $req['uri'], $req['body'], $req['headers']['Authorization']);
        self::assertStringContainsString('"appid"', $req['body']);
    }

    public function testH5OrderUsesMwebEndpoint(): void
    {
        $this->http->stub(['h5_url' => 'https://wx.qq.com/h5']);
        $this->app()->pay()->order('MWEB', [
            'description' => 'test',
            'amount'      => ['total' => 1],
            'out_trade_no' => 'T_H5',
        ]);

        $req = $this->http->last();
        self::assertStringEndsWith('/pay/transactions/h5', $req['uri']);
        $this->assertSignatureValid('POST', $req['uri'], $req['body'], $req['headers']['Authorization']);
    }

    public function testNativeOrderUsesNativeEndpoint(): void
    {
        $this->http->stub(['code_url' => 'weixin://wxpay/bizpayurl']);
        $this->app()->pay()->order('NATIVE', [
            'description' => 'test',
            'amount'      => ['total' => 1],
            'out_trade_no' => 'T_NATIVE',
        ]);

        $req = $this->http->last();
        self::assertStringEndsWith('/pay/transactions/native', $req['uri']);
        $this->assertSignatureValid('POST', $req['uri'], $req['body'], $req['headers']['Authorization']);
    }

    public function testUnsupportedTradeTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不支持的微信支付交易类型');

        $this->app()->pay()->order('FOO', ['description' => 'x']);
    }

    public function testServiceProviderOrderUsesSubFieldsAndSpMchid(): void
    {
        $this->http->stub(['prepay_id' => 'prepay_sp']);
        $this->spApp()->pay()->order('JSAPI', [
            'description'  => 'test',
            'amount'       => ['total' => 1],
            'out_trade_no' => 'T_SP',
        ]);

        $req  = $this->http->last();
        $body = json_decode($req['body'], true);
        self::assertSame('sp_mch_888', $body['sp_mchid']);
        self::assertSame('sub_mch_999', $body['sub_mchid']);
        self::assertSame('wxsub_app_1', $body['sub_appid']);
        self::assertArrayNotHasKey('mchid', $body);

        // V3 签名头中的 mchid 必须是服务商商户号
        preg_match('/mchid="([^"]*)"/', $req['headers']['Authorization'], $mm);
        self::assertSame('sp_mch_888', $mm[1] ?? '');
        $this->assertSignatureValid('POST', $req['uri'], $req['body'], $req['headers']['Authorization']);
    }

    public function testJsapiPromotesOpenidToPayer(): void
    {
        $this->http->stub(['prepay_id' => 'prepay_jsapi']);
        $this->app()->pay()->order('JSAPI', [
            'description'  => 'test',
            'amount'       => ['total' => 1],
            'out_trade_no' => 'T_JSAPI',
            'openid'       => 'OPENID_USER_1',
        ]);

        $body = json_decode($this->http->last()['body'], true);
        self::assertSame('OPENID_USER_1', $body['payer']['openid'] ?? null);
        self::assertArrayNotHasKey('openid', $body);

        // 兼容业务侧直接传 payer.openid
        $this->http->stub(['prepay_id' => 'prepay_jsapi2']);
        $this->app()->pay()->order('JSAPI', [
            'description'  => 'test',
            'amount'       => ['total' => 1],
            'out_trade_no' => 'T_JSAPI2',
            'payer'        => ['openid' => 'OPENID_DIRECT'],
        ]);
        $body2 = json_decode($this->http->last()['body'], true);
        self::assertSame('OPENID_DIRECT', $body2['payer']['openid'] ?? null);
    }

    public function testServiceProviderQueryUsesSpMchidParam(): void
    {
        $this->http->stub(['trade_state' => 'SUCCESS']);
        $this->spApp()->pay()->query('T_SP');

        $req = $this->http->last();
        self::assertStringContainsString('sp_mchid=sp_mch_888', $req['uri']);
    }

    public function testQuerySendsSignedGet(): void
    {
        $this->http->stub(['trade_state' => 'SUCCESS']);
        $result = $this->app()->pay()->query('T1');

        self::assertSame('SUCCESS', $result['trade_state']);

        $req = $this->http->last();
        self::assertSame('GET', $req['method']);
        self::assertSame('', $req['body']);
        $this->assertSignatureValid('GET', $req['uri'], '', $req['headers']['Authorization']);
    }

    public function testCloseSendsSignedPost(): void
    {
        $this->http->stub(['mchid' => 'mch_123456']);
        $this->app()->pay()->close('T1');

        $req = $this->http->last();
        self::assertSame('POST', $req['method']);
        $this->assertSignatureValid('POST', $req['uri'], $req['body'], $req['headers']['Authorization']);
    }

    public function testRefundSendsSignedPost(): void
    {
        $this->http->stub(['refund_id' => 'r1']);
        $result = $this->app()->pay()->refund([
            'out_trade_no' => 'T1',
            'amount'       => ['refund' => 1, 'total' => 1],
        ]);

        self::assertSame('r1', $result['refund_id']);

        $req = $this->http->last();
        $this->assertSignatureValid('POST', $req['uri'], $req['body'], $req['headers']['Authorization']);
    }

    public function testQueryRefundSendsSignedGet(): void
    {
        $this->http->stub(['status' => 'SUCCESS']);
        $this->app()->pay()->queryRefund('R1');

        $req = $this->http->last();
        self::assertSame('GET', $req['method']);
        $this->assertSignatureValid('GET', $req['uri'], '', $req['headers']['Authorization']);
    }

    public function testTradeBillSendsSignedGet(): void
    {
        $this->http->stub(['download_url' => 'https://x']);
        $this->app()->pay()->tradeBill('20240101');

        $req = $this->http->last();
        $this->assertSignatureValid('GET', $req['uri'], '', $req['headers']['Authorization']);
    }

    public function testFundBillSendsSignedGet(): void
    {
        $this->http->stub(['download_url' => 'https://x']);
        $this->app()->pay()->fundBill('20240101');

        $req = $this->http->last();
        $this->assertSignatureValid('GET', $req['uri'], '', $req['headers']['Authorization']);
    }

    public function testMissingKeyPathThrows(): void
    {
        $app = (new Kernel([
            'wechat' => [
                'app_id'        => 'wxapp0000000000',
                'secret'        => 'app-secret',
                'mch_id'        => 'mch_123456',
                'mch_serial_no' => 'serial_no_xyz',
                // 故意不配置 key_path
            ],
        ], $this->http))->wechat()->app();
        \assert($app instanceof WechatApp);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('key_path');
        $app->pay()->order('JSAPI', ['description' => 'x']);
    }

    public function testMissingSerialNoThrows(): void
    {
        $app = (new Kernel([
            'wechat' => [
                'app_id'   => 'wxapp0000000000',
                'secret'   => 'app-secret',
                'mch_id'   => 'mch_123456',
                'key_path' => $this->keyFile,
                // 故意不配置 mch_serial_no
            ],
        ], $this->http))->wechat()->app();
        \assert($app instanceof WechatApp);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mch_serial_no');
        $app->pay()->query('T1');
    }

    /**
     * 校验 Authorization 头中的签名确实由测试私钥对「method + path + body」签发
     */
    private function assertSignatureValid(string $method, string $uri, string $body, string $auth): void
    {
        self::assertMatchesRegularExpression('/^WECHATPAY2-SHA256-RSA2048 (.+)$/', $auth);
        preg_match_all('/(\w+)="([^"]*)"/', $auth, $mm, PREG_SET_ORDER);

        $p = [];
        foreach ($mm as $g) {
            $p[$g[1]] = $g[2];
        }

        $parts = parse_url($uri);
        $path  = (string) ($parts['path'] ?? '/');
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . (string) $parts['query'];
        }

        $message = strtoupper($method) . "\n" . $path . "\n"
            . $p['timestamp'] . "\n" . $p['nonce_str'] . "\n" . $body . "\n";
        $raw = base64_decode($p['signature'], true);
        \assert(is_string($raw));
        $ok = openssl_verify($message, $raw, $this->publicKey, OPENSSL_ALGO_SHA256);

        self::assertSame(1, $ok, '微信 V3 签名应通过公钥验签');
    }
}
