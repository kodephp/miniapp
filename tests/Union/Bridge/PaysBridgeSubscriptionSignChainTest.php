<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 微信 V2「委托代扣」签约（papay/entrustweb）签名拼装全链路测试。
 *
 * 与下单/退款不同：签约不发送同步请求，而是返回一个带 MD5 签名的 GET 跳转链接
 * （{@see \Kode\Pays\Gateway\Wechat\WechatPayGateway::createSubscription()}）。
 * 本测试证明桥接 `subscriptionSubscribe()` 经真实网关产出的链接中，签名为真实 MD5
 * （可用同一 api_key 经 Signer::verifyMd5 重算校验），且缺参时归一为 ApiException。
 */
final class PaysBridgeSubscriptionSignChainTest extends TestCase
{
    private const API_KEY = 'test_api_key_32bytes_long_secret_key_01';

    private const APP_ID = 'wx_app';

    private const MCH_ID = 'mch_1';

    protected function setUp(): void
    {
        Pay::clearCache('wechat');
    }

    /**
     * @return array<string, mixed>
     */
    private function wechatConfig(): array
    {
        return [
            'app_id' => self::APP_ID,
            'mch_id' => self::MCH_ID,
            'api_key' => self::API_KEY,
        ];
    }

    public function testSubscriptionSubscribeReturnsSignedEntrustUrl(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->subscriptionSubscribe([
            'customer_id' => 'USER_888',
            'plan_id' => '1000',
            'notify_url' => 'https://example.com/notify',
        ]);

        // 返回可跳转的签约链接（GET，papay/entrustweb）
        self::assertSame('GET', $result['method']);
        self::assertStringContainsString('papay/entrustweb', $result['url']);
        self::assertSame('USER_888', $result['contract_code']);
        self::assertSame('1000', $result['plan_id']);

        // 从链接中解析出已签名的查询串，验证 MD5 签名真实可信
        // 注意：Signer::verifyMd5 内部会自行移除 sign 字段，故此处保留 sign 传入
        $query = (string) parse_url($result['url'], PHP_URL_QUERY);
        parse_str($query, $parsed);
        /** @var array<string, string> $params */
        $params = [];
        foreach ($parsed as $key => $value) {
            $params[(string) $key] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        self::assertArrayHasKey('sign', $params);
        self::assertTrue(
            Signer::verifyMd5($params, self::API_KEY),
            '签约链接内嵌签名未能通过独立 MD5 校验',
        );
    }

    public function testSubscriptionSubscribeMissingParamNormalizedToApiException(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 缺 plan_id / notify_url → 网关 validateRequired 抛 PayException → 桥接归一 ApiException
        $this->expectException(ApiException::class);

        $adapter->subscriptionSubscribe([
            'customer_id' => 'USER_888',
        ]);
    }
}
