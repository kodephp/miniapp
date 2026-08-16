<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use PHPUnit\Framework\TestCase;

/**
 * QQ 异步回调验签（MD5 + api_key 后缀）端到端测试。
 *
 * 与微信 V2 的 MD5（{@see PaysBridgeNotifyVerifyTest}）同属 MD5 族，但 QQ 的规范化规则不同：
 * ksort 后用 http_build_query(RFC3986) 拼接再追加 '&key=' . api_key 做 MD5（大写）。
 * 这是跨渠道「钱到账确认路径」在 QQ 侧的真实闭环，目前为未证缺口。
 * 全程不触网，用与 QQGateway::verifyNotify 完全一致的规则自签报文。
 */
final class PaysBridgeQqNotifyVerifyTest extends TestCase
{
    private const APP_ID = 'qq_app_20260816';

    private const MCH_ID = 'qq_mch_001';

    private const API_KEY = 'qq_api_key_9c2e7b';

    private const SERIAL_NO = 'qq_serial_001';

    private const PRIVATE_KEY = 'qq_dummy_private_key_not_used_for_verify';

    protected function setUp(): void
    {
        // 避免跨测试缓存到其它 qq 网关配置
        Pay::clearCache('qq');
    }

    /**
     * QQ 网关构造需 5 个必填字段，但 verifyNotify 仅用 api_key；
     * serial_no / private_key 为 V3 请求鉴权所用，此处给 dummy 值。
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'app_id' => self::APP_ID,
            'mch_id' => self::MCH_ID,
            'api_key' => self::API_KEY,
            'serial_no' => self::SERIAL_NO,
            'private_key' => self::PRIVATE_KEY,
        ];
    }

    /**
     * 用与 QQGateway::verifyNotify 完全一致的规则自签报文：
     * ksort → http_build_query(RFC3986) → '&key=' . api_key → strtoupper(md5)。
     *
     * @param array<string, string> $data
     */
    private function sign(array $data): string
    {
        $payload = $data;
        unset($payload['sign']);
        ksort($payload);
        $string = http_build_query($payload, '', '&', PHP_QUERY_RFC3986) . '&key=' . self::API_KEY;

        return strtoupper(md5($string));
    }

    public function testValidQqNotifyDecodesToBusinessPayload(): void
    {
        $data = [
            'out_trade_no' => 'QQ20260816001',
            'transaction_id' => '4200001234567890',
            'trade_state' => 'SUCCESS',
            'result_code' => 'SUCCESS',
            'total_fee' => '100',
        ];
        $data['sign'] = $this->sign($data);

        $adapter = PaysBridge::notifyAdapter(Channel::Qq, fn () => $this->config());

        /** @var array<string, mixed> $result */
        $result = $adapter->decode($data);

        // 验签通过：返回原始业务报文（out_trade_no / transaction_id 完整可信）
        self::assertSame('QQ20260816001', $result['out_trade_no']);
        self::assertSame('4200001234567890', $result['transaction_id']);
        // 独立重算签名，证明桥接走的签名是真实网关 MD5，而非桩
        self::assertSame($data['sign'], $result['sign']);
    }

    public function testTamperedQqNotifyRejected(): void
    {
        $data = [
            'out_trade_no' => 'QQ20260816001',
            'transaction_id' => '4200001234567890',
            'trade_state' => 'SUCCESS',
            'result_code' => 'SUCCESS',
            'total_fee' => '100',
        ];
        $data['sign'] = $this->sign($data);

        // 篡改业务字段但不重签
        $data['total_fee'] = '999';

        $adapter = PaysBridge::notifyAdapter(Channel::Qq, fn () => $this->config());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/验签失败/');
        $adapter->decode($data);
    }

    public function testMissingSignQqNotifyRejected(): void
    {
        $data = [
            'out_trade_no' => 'QQ20260816001',
            'transaction_id' => '4200001234567890',
            'trade_state' => 'SUCCESS',
            'result_code' => 'SUCCESS',
            'total_fee' => '100',
        ];

        $adapter = PaysBridge::notifyAdapter(Channel::Qq, fn () => $this->config());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/验签失败/');
        $adapter->decode($data);
    }
}
