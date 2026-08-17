<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\AlipaySigningFakeHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 支付宝订阅生命周期（取消 / 查询 / 暂停 / 恢复）桥接 e2e。
 *
 * 与 {@see PaysBridgeSubscriptionLifecycleTest}（仅覆盖微信 V2 委托代扣：deletecontract / querycontract
 * 经 MD5 签名）形成跨渠道对照：本测试覆盖支付宝侧真正「出站」的订阅操作——取消订阅
 * （alipay.user.agreement.unsign）与查询订阅（alipay.user.agreement.query），二者均经真实 AlipayGateway
 * + FakePaysHttpClient 走通「RSA2 签名 / 报文拼装 / 响应解析」真实代码路径而不触网。
 *
 * 同时锁定：支付宝周期扣款无「暂停 / 恢复」端点（pauseSubscription / resumeSubscription 在网关层抛
 * methodNotSupported），桥接经 invokeGateway 归一为 ApiException，而非静默下行或 Call to undefined method。
 *
 * 断言核心：真实网关对出站请求做了 RSA2 签名（Signer::rsa2），且签名可用同一商户公钥
 * openssl_verify(SHA256)===1 重新校验通过——即「桥接只做路由，签名由真实 kode/pays 网关完成」的全链路证据。
 */
final class PaysBridgeAlipaySubscriptionLifecycleSignChainTest extends TestCase
{
    private AlipaySigningFakeHttpClient $fake;

    private string $privateKeyPem;

    private string $publicKeyPem;

    protected function setUp(): void
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        self::assertNotFalse($res, 'openssl 必须可用以生成测试 RSA 密钥');

        $priv = '';
        openssl_pkey_export($res, $priv);
        $this->privateKeyPem = $priv;

        $detail = openssl_pkey_get_details($res);
        self::assertNotFalse($detail, 'openssl_pkey_get_details 必须返回密钥明细');
        $this->publicKeyPem = (string) $detail['key'];

        // 取消 / 查询订阅返回成功 JSON（不触网 POST），假客户端记录出站数据供签名断言
        $this->fake = new AlipaySigningFakeHttpClient('alipay_user_agreement_unsign_response', [
            'code'        => '10000',
            'msg'         => 'Success',
            'agreement_no' => 'AGR_123',
        ]);
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    private function adapter(): PaysBridgePayAdapter
    {
        return PaysBridge::adapter(Channel::AlipayMini, fn () => [
            'app_id'      => 'alipay_app',
            'private_key' => $this->privateKeyPem,
            'public_key'  => $this->publicKeyPem,
            'notify_url'  => 'https://example.com/notify',
        ]);
    }

    /**
     * 重建网关同源 Signer::buildQueryString 基串，用商户公钥独立复核出站 RSA2 签名。
     *
     * @param array<string, mixed> $data
     */
    private function assertOutboundRsa2Verified(array $data, string $label): void
    {
        $sign = (string) ($data['sign'] ?? '');
        self::assertNotEmpty($sign, "{$label} 出站请求必须携带 RSA2 签名");

        $payload = $data;
        unset($payload['sign']);
        $plain = Signer::buildQueryString($payload);

        $ok = openssl_verify($plain, (string) base64_decode($sign), $this->publicKeyPem, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $ok, "{$label} 出站请求必须用同一商户公钥 RSA2 验签通过");
    }

    public function testCancelSubscriptionSignsAndParsesViaRealGateway(): void
    {
        $result = $this->adapter()->subscriptionCancel('AGR_123');

        // 真实网关解析成功响应（FakePaysHttpClient 返回的支付宝成功报文）
        self::assertSame('10000', $result['code'] ?? null, '取消订阅须返回成功 code=10000');
        self::assertSame('AGR_123', $result['agreement_no'] ?? null);

        // 出站端点必须是 alipay.user.agreement.unsign（解约）
        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        self::assertSame('alipay.user.agreement.unsign', $data['method'] ?? '', '解约接口 method 必须为 alipay.user.agreement.unsign');
        self::assertSame('RSA2', $data['sign_type'] ?? '', '支付宝签名类型必须为 RSA2');

        // biz_content 必须携带协议号
        /** @var array<string, mixed> $biz */
        $biz = is_array(json_decode((string) ($data['biz_content'] ?? ''), true)) ? json_decode((string) ($data['biz_content'] ?? ''), true) : [];
        self::assertSame('AGR_123', $biz['agreement_no'] ?? '', '解约 biz_content 必须携带 agreement_no');

        // 全链路核心：取消订阅请求必须做了 RSA2 签名且可复核通过
        $this->assertOutboundRsa2Verified($data, '取消订阅');
    }

    public function testGetSubscriptionSignsAndParsesViaRealGateway(): void
    {
        $result = $this->adapter()->subscriptionGet('AGR_123');

        self::assertSame('10000', $result['code'] ?? null, '查询订阅须返回成功 code=10000');

        // 出站端点必须是 alipay.user.agreement.query（查询签约关系）
        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        self::assertSame('alipay.user.agreement.query', $data['method'] ?? '', '查询接口 method 必须为 alipay.user.agreement.query');

        // biz_content 必须携带协议号
        /** @var array<string, mixed> $biz */
        $biz = is_array(json_decode((string) ($data['biz_content'] ?? ''), true)) ? json_decode((string) ($data['biz_content'] ?? ''), true) : [];
        self::assertSame('AGR_123', $biz['agreement_no'] ?? '', '查询 biz_content 必须携带 agreement_no');

        // 全链路核心：查询订阅请求同样做了 RSA2 签名且可复核通过
        $this->assertOutboundRsa2Verified($data, '查询订阅');
    }

    public function testGetSubscriptionByExternalAgreementNoRoutesCorrectly(): void
    {
        // 以 ext: 前缀传入商户侧协议号，网关应转 external_agreement_no
        $this->adapter()->subscriptionGet('ext:EXT_001');

        /** @var array<string, mixed> $data */
        $data = $this->fake->lastData ?? [];
        self::assertSame('alipay.user.agreement.query', $data['method'] ?? '');

        /** @var array<string, mixed> $biz */
        $biz = is_array(json_decode((string) ($data['biz_content'] ?? ''), true)) ? json_decode((string) ($data['biz_content'] ?? ''), true) : [];
        self::assertSame('EXT_001', $biz['external_agreement_no'] ?? '', 'ext: 前缀须映射为 external_agreement_no');
        self::assertSame('CYCLE_PAY_AUTH_P', $biz['personal_product_code'] ?? '', 'ext: 须携带个人签约产品码');
        self::assertSame('INDUSTRY|DEFAULT_SCENE', $biz['sign_scene'] ?? '', 'ext: 须携带签约场景');

        $this->assertOutboundRsa2Verified($data, 'ext: 查询订阅');
    }

    public function testPauseSubscriptionUnsupportedNormalizedToApiException(): void
    {
        // 支付宝周期扣款无「暂停」端点：网关抛 methodNotSupported → 桥接 invokeGateway 归一为 ApiException
        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/pauseSubscription/');

        $this->adapter()->subscriptionPause('AGR_123');
    }

    public function testResumeSubscriptionUnsupportedNormalizedToApiException(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/resumeSubscription/');

        $this->adapter()->subscriptionResume('AGR_123');
    }
}
