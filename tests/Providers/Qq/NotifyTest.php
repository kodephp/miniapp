<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Qq;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Qq\Modules\Pay;
use Kode\MiniApp\Providers\Qq\QqApp;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * QQ 支付回调通知模块测试
 *
 * 验证 QqApp::notify() 的 XML 解析与 MD5 验签（复用 Pay::sign，保证与下单算法一致）。
 */
final class NotifyTest extends TestCase
{
    private function buildApp(bool $withKey = true): QqApp
    {
        $config = [
            'app_id' => 'qqapp000000000000',
            'secret' => 'qq-secret',
            'mch_id' => 'qq-mch',
            'cache'  => new ArrayCache(),
        ];
        if ($withKey) {
            $config['api_key'] = 'qq-api-key';
        }

        $kernel = new Kernel(['qq' => $config], new FakeHttpClient());
        $app    = $kernel->qq()->app();
        self::assertInstanceOf(QqApp::class, $app);

        return $app;
    }

    /**
     * 用与下单一致的 Pay::sign 构造已签名的回调 XML
     *
     * @param array<string, mixed> $payload
     */
    private function signedXml(array $payload, string $key): string
    {
        $payload['sign'] = Pay::sign($payload, $key);

        $xml = '<xml>';
        foreach ($payload as $k => $v) {
            $xml .= is_numeric($v)
                ? "<{$k}>{$v}</{$k}>"
                : "<{$k}><![CDATA[{$v}]]></{$k}>";
        }

        return $xml . '</xml>';
    }

    public function testDecodeVerifiesValidSignature(): void
    {
        $app = $this->buildApp();

        $payload = [
            'appid'         => 'qqapp000000000000',
            'mch_id'        => 'qq-mch',
            'out_trade_no'  => 'ORDER_1',
            'transaction_id' => 'TXN_1',
            'total_fee'     => 100,
            'openid'        => 'OPENID_1',
            'result_code'   => 'SUCCESS',
        ];
        $xml     = $this->signedXml($payload, 'qq-api-key');
        $decoded = $app->notify()->decode($xml);

        self::assertSame('ORDER_1', $decoded['out_trade_no'] ?? null);
        self::assertSame('TXN_1', $decoded['transaction_id'] ?? null);
        self::assertSame('OPENID_1', $decoded['openid'] ?? null);
        self::assertSame('SUCCESS', $decoded['result_code'] ?? null);
    }

    public function testDecodeThrowsOnBadSignature(): void
    {
        $app = $this->buildApp();

        $xml = '<xml>'
            . '<out_trade_no><![CDATA[ORDER_1]]></out_trade_no>'
            . '<total_fee>100</total_fee>'
            . '<sign><![CDATA[deadbeef]]></sign>'
            . '</xml>';

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('QQ 支付回调签名验证失败');
        $app->notify()->decode($xml);
    }

    public function testVerifySkipsWhenKeyNotConfigured(): void
    {
        $app = $this->buildApp(false);

        $xml = '<xml>'
            . '<out_trade_no><![CDATA[ORDER_1]]></out_trade_no>'
            . '<sign><![CDATA[whatever]]></sign>'
            . '</xml>';

        // 未配置 api_key：跳过验签，不抛异常
        $decoded = $app->notify()->decode($xml);
        self::assertSame('ORDER_1', $decoded['out_trade_no'] ?? null);
    }

    public function testVerifyReturnsFalseOnEmptySign(): void
    {
        $app = $this->buildApp();
        self::assertFalse($app->notify()->verify(['out_trade_no' => 'ORDER_1']));
    }
}
