<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\DouyinSigningFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 抖音支付核心下单 / 退款 / 分账 MD5+salt 签名链 e2e。
 *
 * 目标：证明真实 DouyinPayGateway 在 createOrder / queryOrder / refund / queryRefund /
 * createProfitSharing / queryProfitSharing 六个方法上，确实以 app_id / merchant_id / salt 配置
 * 对「ksort 后的业务字段」做 md5(buildQueryString . '&salt=' . salt) 签名，并经
 * DouyinSigningFakeHttpClient 返回 err_no=0 成功报文后解析为关联数组。
 *
 * 验证用「同一 salt」独立重算签名串，与微信 V2 测试用 Signer::verifyMd5 重算、支付宝用
 * Signer::rsa2 重算同属「真实网关签名可被独立复核」的强证据，覆盖抖音这一 MD5+盐变体。
 *
 * closeOrder 在抖音网关为「主动关闭订单不支持」，必须大声失败（PayException → ApiException），
 * 而非静默返回假成功——本测试断言其归一为 ApiException，证明无静默失败。
 */
final class PaysBridgeDouyinCoreSignChainTest extends TestCase
{
    private DouyinSigningFakeHttpClient $fake;

    private const SALT = 'unit_test_douyin_salt_0123456789';

    protected function setUp(): void
    {
        $this->fake = new DouyinSigningFakeHttpClient();
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    private function adapter(): PaysBridgePayAdapter
    {
        return PaysBridge::adapter(Channel::DouyinMini, fn () => [
            'app_id'      => 'douyin_app',
            'merchant_id' => 'douyin_mch',
            'salt'        => self::SALT,
        ]);
    }

    /**
     * 独立重算抖音 MD5+salt 签名并断言与网关发出的 sign 完全一致。
     *
     * 网关在签名后才追加 timestamp，故重算须排除 timestamp 与 sign 两个字段，
     * 否则规范化串会与网关签名时不一致。
     */
    private function assertDouyinSignatureValid(): void
    {
        $data = $this->fake->lastData;
        self::assertIsArray($data, '捕获的请求数据必须是数组');
        self::assertArrayHasKey('sign', $data, '抖音请求必须带 sign');
        self::assertArrayHasKey('timestamp', $data, '抖音请求必须带 timestamp（sign 之后追加，不参与签名）');

        $verify = $data;
        unset($verify['sign']);
        unset($verify['timestamp']);

        $expected = md5(Signer::buildQueryString($verify) . '&salt=' . self::SALT);
        self::assertSame($expected, $data['sign'], '抖音 MD5+salt 签名必须可被独立重算核验');
    }

    public function testCreateOrderSignsMd5AndReturnsParsedResponse(): void
    {
        $result = $this->adapter()->createOrder([
            'out_order_no' => 'D20260817',
            'total_amount' => 100,
            'subject'      => '测试商品',
            'body'         => '测试描述',
            'valid_time'   => 1800,
            'notify_url'   => 'https://example.com/notify',
        ]);

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('api/apps/ecpay/v1/create_order', $this->fake->lastUrl);
        $this->assertDouyinSignatureValid();
        self::assertSame('D20260817', $result['out_order_no'] ?? null);
        self::assertSame(0, $result['err_no'] ?? null);
    }

    public function testQueryOrderSignsMd5AndReturnsParsedResponse(): void
    {
        $result = $this->adapter()->queryOrder('D20260817');

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('api/apps/ecpay/v1/query_order', $this->fake->lastUrl);
        $this->assertDouyinSignatureValid();
        self::assertSame('D20260817', $result['out_order_no'] ?? null);
    }

    public function testRefundSignsMd5AndReturnsParsedResponse(): void
    {
        $result = $this->adapter()->refund([
            'out_refund_no' => 'DR20260817',
            'refund_amount' => 100,
            'reason'        => '测试退款',
            'out_order_no'  => 'D20260817',
        ]);

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('api/apps/ecpay/v1/create_refund', $this->fake->lastUrl);
        $this->assertDouyinSignatureValid();
        self::assertSame('DR20260817', $result['out_refund_no'] ?? null);
    }

    public function testQueryRefundSignsMd5AndReturnsParsedResponse(): void
    {
        $result = $this->adapter()->queryRefund('DR20260817');

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('api/apps/ecpay/v1/query_refund', $this->fake->lastUrl);
        $this->assertDouyinSignatureValid();
        self::assertSame('DR20260817', $result['out_refund_no'] ?? null);
    }

    public function testProfitSharingCreateSignsMd5AndReturnsParsedResponse(): void
    {
        $result = $this->adapter()->profitSharingCreate([
            'out_order_no' => 'D20260817',
            'transaction_id' => 'D20260817',
            'receivers'    => [
                ['type' => 'MERCHANT', 'account' => 'uid_1', 'amount' => 10, 'description' => '分账'],
            ],
        ]);

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('api/apps/ecpay/v1/settle', $this->fake->lastUrl);
        $this->assertDouyinSignatureValid();
        self::assertSame('D20260817', $result['out_settle_no'] ?? null);
    }

    public function testProfitSharingQuerySignsMd5AndReturnsParsedResponse(): void
    {
        $result = $this->adapter()->profitSharingQuery('S20260817');

        self::assertSame('POST', $this->fake->lastMethod);
        self::assertStringContainsString('api/apps/ecpay/v1/query_settle', $this->fake->lastUrl);
        $this->assertDouyinSignatureValid();
        self::assertSame('S20260817', $result['out_settle_no'] ?? null);
    }

    public function testCloseOrderLoudFailsBecauseDouyinDoesNotSupportIt(): void
    {
        $caught = null;
        try {
            $this->adapter()->closeOrder('D20260817');
        } catch (ApiException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, '抖音不支持关单必须归一为 ApiException（无静默成功）');
        self::assertStringContainsString('不支持', $caught->getMessage());
    }
}
