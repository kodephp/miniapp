<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 抖音异步回调验签（MD5 + salt）端到端测试。
 *
 * 与微信 V2 的 MD5（{@see PaysBridgeNotifyVerifyTest}）、支付宝的 RSA2
 * （{@see PaysBridgeAlipayNotifyVerifyTest}）对称，但抖音的签名规则是「加盐 salt」：
 * sign = md5(buildQueryString(params) . '&salt=' . salt)。这是跨渠道「钱到账确认路径」
 * 在抖音侧的真实闭环——目前其它已桥接渠道都已证，抖音是最后一处未证缺口。
 * 全程不触网，用与网关完全一致的 sign() 规则自签报文。
 */
final class PaysBridgeDouyinNotifyVerifyTest extends TestCase
{
    private const APP_ID = 'douyin_app_20260816';

    private const MERCHANT_ID = 'douyin_merchant_001';

    private const SALT = 'douyin_salt_secret_7f3a';

    protected function setUp(): void
    {
        // 避免跨测试缓存到其它 douyin 网关配置
        Pay::clearCache('douyin');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'app_id' => self::APP_ID,
            'merchant_id' => self::MERCHANT_ID,
            'salt' => self::SALT,
        ];
    }

    /**
     * 用与 DouyinPayGateway::sign() 完全一致的规则自签报文：
     * md5(buildQueryString(params) . '&salt=' . salt)。
     *
     * @param array<string, string> $data
     */
    private function sign(array $data): string
    {
        return md5(Signer::buildQueryString($data) . '&salt=' . self::SALT);
    }

    public function testValidDouyinNotifyDecodesToBusinessPayload(): void
    {
        $data = [
            'out_order_no' => 'MER20260816001',
            'order_id' => '6930001000001',
            'trade_status' => 'SUCCESS',
            'total_amount' => '100',
            'timestamp' => '1692134400',
        ];
        $data['sign'] = $this->sign($data);

        $adapter = PaysBridge::notifyAdapter(Channel::DouyinMini, fn () => $this->config());

        /** @var array<string, mixed> $result */
        $result = $adapter->decode($data);

        // 验签通过：返回原始业务报文（out_order_no / order_id 完整可信）
        self::assertSame('MER20260816001', $result['out_order_no']);
        self::assertSame('6930001000001', $result['order_id']);
        // 独立重算签名，证明桥接走的签名是真实网关 salt-MD5，而非桩
        self::assertSame($data['sign'], $result['sign']);
        self::assertSame(
            hash_equals($this->sign($result), (string) $result['sign']),
            true,
        );
    }

    public function testTamperedDouyinNotifyRejected(): void
    {
        $data = [
            'out_order_no' => 'MER20260816001',
            'order_id' => '6930001000001',
            'trade_status' => 'SUCCESS',
            'total_amount' => '100',
            'timestamp' => '1692134400',
        ];
        $data['sign'] = $this->sign($data);

        // 篡改业务字段但不重签
        $data['total_amount'] = '999';

        $adapter = PaysBridge::notifyAdapter(Channel::DouyinMini, fn () => $this->config());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/验签失败/');
        $adapter->decode($data);
    }

    public function testMissingSignDouyinNotifyRejected(): void
    {
        $data = [
            'out_order_no' => 'MER20260816001',
            'order_id' => '6930001000001',
            'trade_status' => 'SUCCESS',
            'total_amount' => '100',
            'timestamp' => '1692134400',
        ];

        $adapter = PaysBridge::notifyAdapter(Channel::DouyinMini, fn () => $this->config());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/验签失败/');
        $adapter->decode($data);
    }
}
