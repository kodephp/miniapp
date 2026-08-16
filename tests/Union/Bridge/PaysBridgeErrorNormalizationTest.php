<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FailureXmlFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Core\PayException;
use Kode\Pays\Facade\Pay;
use PHPUnit\Framework\TestCase;

/**
 * PaysBridge 错误归一化契约测试。
 *
 * 证明 kode/pays 的 PayException（内部 ERROR_* 码 + 平台原始 gateway_code）
 * 经 PaysBridge::invokeGateway 归一为 miniapp 的 ApiException 时：
 *   1. 内部 ERROR_* 码（1000-1008）1:1 成为 ApiException::errorCode()；
 *   2. 平台原始 err_code / code 原样落入 payload['gateway_code'] / ['gateway_message']，不被翻译或丢弃；
 *   3. 原始 PayException 作为 previous 异常链完整保留；
 *   4. action() 带「支付[渠道]能力」场景串、platform() 为 null（支付层不绑定身份平台）。
 *
 * 另含一例「真实 WechatPayGateway 返回 result_code=FAIL + err_code」端到端，
 * 验证平台原始码从真实网关 -> PayException -> ApiException::payload 的透传链路。
 */
final class PaysBridgeErrorNormalizationTest extends TestCase
{
    protected function setUp(): void
    {
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    /**
     * 内部参数错误码（1004）1:1 成为 errorCode()，无平台原始码。
     */
    public function testParamErrorCodeMapsOneToOneToApiExceptionErrorCode(): void
    {
        $caught = $this->normalized(
            fn () => throw PayException::paramError('裂变红包 total_num 必须 >= 3'),
            'redPacketGroup',
        );

        self::assertSame(PayException::ERROR_PARAM, $caught->errorCode());
        self::assertSame(PayException::ERROR_PARAM, $caught->getCode());
        self::assertNull($caught->payload()['gateway_code'] ?? null);
        self::assertNull($caught->payload()['gateway_message'] ?? null);
    }

    /**
     * 平台业务错误（1005）保留原始 err_code / err_code_des 到 payload。
     */
    public function testGatewayErrorCodePreservesPlatformRawCodeInPayload(): void
    {
        $caught = $this->normalized(
            fn () => throw PayException::gatewayError('微信支付业务失败', 'AMOUNT_LIMIT', '付款金额超限'),
            'transferSingle',
        );

        self::assertSame(PayException::ERROR_GATEWAY, $caught->errorCode());
        self::assertSame('AMOUNT_LIMIT', $caught->payload()['gateway_code'] ?? null);
        self::assertSame('付款金额超限', $caught->payload()['gateway_message'] ?? null);
    }

    /**
     * 网关不支持方法（1008）归一为 ApiException，且信息可识别。
     */
    public function testMethodNotSupportedNormalizedToApiException(): void
    {
        $caught = $this->normalized(
            fn () => throw PayException::methodNotSupported('wechat', 'settleToPayout'),
            'settlementToPayout',
        );

        self::assertSame(PayException::ERROR_METHOD_NOT_SUPPORTED, $caught->errorCode());
        self::assertStringContainsString('settleToPayout', $caught->getMessage());
        self::assertStringContainsString('不支持', $caught->getMessage());
    }

    /**
     * 签名错误（1003）与配置错误（1001）同样 1:1 归一。
     */
    public function testSignAndConfigErrorCodesNormalized(): void
    {
        $cases = [
            [PayException::signError('响应签名验证失败'), PayException::ERROR_SIGN],
            [PayException::configError('缺少 serial_no'), PayException::ERROR_CONFIG],
        ];

        foreach ($cases as [$ex, $code]) {
            $caught = $this->normalized(static fn () => throw $ex, 'queryTransfer');
            self::assertSame($code, $caught->errorCode(), "码 {$code} 应 1:1 归一");
        }
    }

    /**
     * 原始 PayException 作为 previous 异常链完整保留，业务侧可回溯。
     */
    public function testPreviousExceptionChainIntact(): void
    {
        $caught = $this->normalized(
            fn () => throw PayException::gatewayError('微信支付业务失败', 'SYSTEMERROR', '系统错误'),
            'transferSingle',
        );

        self::assertInstanceOf(PayException::class, $caught->getPrevious());
        self::assertSame('SYSTEMERROR', $caught->getPrevious()->getGatewayCode());
    }

    /**
     * action() 携带「支付[渠道]能力」场景串，platform() 为 null（支付层不绑定身份平台）。
     */
    public function testActionAndPlatformMetadata(): void
    {
        $caught = $this->normalized(
            fn () => throw PayException::paramError('参数缺失'),
            'transferSingle',
        );

        self::assertNull($caught->platform());
        self::assertStringContainsString('transferSingle', $caught->action() ?? '');
        self::assertStringStartsWith('支付[', $caught->action() ?? '');
    }

    /**
     * 真实 WechatPayGateway 返回 result_code=FAIL + err_code，
     * 平台原始码应经 PayException -> ApiException::payload 透传，不被翻译或丢弃。
     */
    public function testRealGatewayBusinessFailureCarriesRawErrCodeInPayload(): void
    {
        $fake = new FailureXmlFakeHttpClient('unit_test_api_key_0123456789');
        Pay::setHttpClient($fake);

        $adapter = PaysBridge::adapter(
            Channel::WechatMini,
            static fn () => [
                'app_id'  => 'wx_app',
                'mch_id'  => 'mch_1',
                'api_key' => 'unit_test_api_key_0123456789',
            ],
        );

        $caught = null;
        try {
            $adapter->transferSingle([
                'out_biz_no' => 'S_ERR_001',
                'amount'     => 100,
                'recipient'  => ['type' => 'openid', 'account' => 'OPENID_X', 'name' => '张三'],
            ]);
        } catch (ApiException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, '预期抛出 ApiException');
        self::assertSame(PayException::ERROR_GATEWAY, $caught->errorCode());
        // 微信 V2 失败路径：err_code -> gateway_code；err_code_des -> 异常 message；
        // 该路径未传 gateway_message（第 3 构造参数），故 payload['gateway_message'] 为 null。
        self::assertSame('AMOUNT_LIMIT', $caught->payload()['gateway_code'] ?? null);
        self::assertNull($caught->payload()['gateway_message'] ?? null);
        self::assertStringContainsString('付款金额超限', $caught->getMessage());
        self::assertInstanceOf(PayException::class, $caught->getPrevious());
    }

    /**
     * 驱动 invokeGateway 并捕获归一后的 ApiException（断言其必定抛出）。
     *
     * @param \Closure():mixed $fn
     */
    private function normalized(\Closure $fn, string $capability): ApiException
    {
        $caught = null;
        try {
            PaysBridge::invokeGateway($fn, Channel::WechatMini, $capability);
        } catch (ApiException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, '预期抛出 ApiException');

        return $caught;
    }
}
