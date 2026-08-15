<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\NoCapabilityGateway;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\AdvancedPayAdapter;
use Kode\MiniApp\Union\Union;
use Kode\Pays\Core\GatewayFactory;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Gateway\Wechat\WechatPayGateway;
use PHPUnit\Framework\TestCase;

/**
 * 验证 PaysBridge 高级支付能力（分账 / 转账 / 对账）委托到真实 kode/pays 网关。
 *
 * 2.0 起 kode/pays 为硬依赖（vendor 已安装真实 kode/pays）。本测试通过 {@see FakePaysHttpClient}
 * 注入真实网关实例，走通「参数校验 / 签名 / 报文拼装 / 响应解析」真实代码路径（含分账 / 转账 /
 * 对账特色方法）而不触网，并验证：
 *  - 适配器实现 {@see AdvancedPayAdapter}；
 *  - 分账 / 转账 / 对账方法正确转发到对应网关方法并返回解析后的业务数组；
 *  - 网关不支持某项能力时（method_exists 守卫）抛清晰异常。
 */
final class PaysBridgeAdvancedTest extends TestCase
{
    private const API_KEY = 'unit_test_api_key_0123456789';

    private FakePaysHttpClient $fake;

    protected function setUp(): void
    {
        $this->fake = new FakePaysHttpClient(self::API_KEY);
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    /**
     * @return array<string, mixed>
     */
    private function wechatConfig(): array
    {
        return ['app_id' => 'wx_app', 'mch_id' => 'mch_1', 'api_key' => self::API_KEY];
    }

    public function testAdapterImplementsAdvancedPayAdapter(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(AdvancedPayAdapter::class, $adapter);
        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        foreach (
            [
                'profitSharingCreate', 'profitSharingQuery', 'profitSharingReturn',
                'profitSharingQueryReturn', 'profitSharingUnfreeze', 'transferSingle',
                'transferBatch', 'transferQuery', 'transferReceipt',
                'reconciliationDownloadBill', 'reconciliationDownloadFundFlow', 'reconciliationParseBill',
            ] as $method
        ) {
            self::assertTrue(method_exists($adapter, $method), "PaysBridgePayAdapter 缺少高级方法 {$method}");
        }
    }

    public function testWechatSupportsAllThreeCapabilities(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertTrue($adapter->supportsProfitSharing());
        self::assertTrue($adapter->supportsTransfer());
        self::assertTrue($adapter->supportsReconciliation());
    }

    public function testQqSupportsNoAdvancedCapability(): void
    {
        $adapter = PaysBridge::adapter(Channel::Qq, fn () => $this->wechatConfig());

        self::assertFalse($adapter->supportsProfitSharing());
        self::assertFalse($adapter->supportsTransfer());
        self::assertFalse($adapter->supportsReconciliation());
    }

    public function testBaiduSupportsNoAdvancedCapability(): void
    {
        // 百度网关未在 kode/pays 注册，能力发现须返回 false（而非抛异常）
        $adapter = PaysBridge::adapter(Channel::BaiduMini, fn () => $this->wechatConfig());

        self::assertFalse($adapter->supportsProfitSharing());
        self::assertFalse($adapter->supportsTransfer());
        self::assertFalse($adapter->supportsReconciliation());
    }

    public function testProfitSharingCreateDelegatesToWechatGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->profitSharingCreate([
            'transaction_id' => 'WXTXN1',
            'out_order_no'   => 'PS_1',
            'receivers'      => [
                ['type' => 'MERCHANT_ID', 'account' => 'mch_2', 'amount' => 100, 'description' => '分账'],
            ],
        ]);

        // 真实网关解析签名成功响应，返回 return_code=SUCCESS
        self::assertSame('SUCCESS', $result['return_code']);
        // 请求须打到微信分账端点
        self::assertStringContainsString('secapi/pay/profitsharing', $this->fake->lastUrl ?? '');
    }

    public function testTransferSingleDelegatesToWechatGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->transferSingle([
            'out_biz_no' => 'BIZ_1',
            'amount'     => 100,
            'recipient'  => ['type' => 'openid', 'account' => 'OPEN_1', 'name' => '张三'],
        ]);

        self::assertSame('SUCCESS', $result['return_code']);
        self::assertStringContainsString('mmpaymkttransfers/promotion/transfers', $this->fake->lastUrl ?? '');
    }

    public function testReconciliationDownloadBillDelegatesToWechatGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        $result = $adapter->reconciliationDownloadBill(['bill_date' => '20260814']);

        // 微信对账单接口返回含 bill_date 与解析后记录列表的结构
        self::assertSame('20260814', $result['bill_date']);
        self::assertIsArray($result['records']);
    }

    public function testReconciliationParseBillParsesCsv(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        // 微信对账单 CSV：首行为表头（忽略），数据行以反引号（`）转义字段
        $csv = "交易时间,公众账号ID,商户号,子商户号,设备号,微信订单号,商户订单号,"
            . "用户标识,交易类型,交易状态,付款银行,货币种类\n"
            . "`2026-08-14 10:00:00`,`wx_app`,`mch_1`,`sub`,`dev`,`WXTXN1`,`OUT1`,"
            . "`OPEN1`,`JSAPI`,`SUCCESS`,`BANK`,`CNY`";

        $records = $adapter->reconciliationParseBill($csv);

        self::assertNotEmpty($records);
        self::assertSame('OUT1', $records[0]['out_trade_no'] ?? '');
    }

    /**
     * method_exists 守卫：当某渠道的网关未实现某项特色方法时，抛清晰异常而非致命错误。
     *
     * 这里临时把 wechat 网关替换为「未实现分账」的最小网关，验证守卫行为后还原，
     * 避免污染其它测试的网关注册表。
     */
    public function testUnsupportedCapabilityThrowsClearException(): void
    {
        Pay::clearCache('wechat');
        GatewayFactory::unregister('wechat');
        Pay::register('wechat', NoCapabilityGateway::class);

        try {
            $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('分账');

            $adapter->profitSharingCreate(['transaction_id' => 'X', 'out_order_no' => 'Y', 'receivers' => []]);
        } finally {
            GatewayFactory::unregister('wechat');
            Pay::register('wechat', WechatPayGateway::class);
            Pay::clearCache('wechat');
        }
    }

    /**
     * 端到端验证 Union::advancedPay() 便捷入口返回真实 PaysBridge 适配器并可调用特色方法。
     */
    public function testUnionAdvancedPayReturnsBridgeAdapter(): void
    {
        $union   = $this->buildUnion();
        $adapter = $union->advancedPay(Channel::WechatMini);

        self::assertInstanceOf(AdvancedPayAdapter::class, $adapter);
        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        $result = $adapter->transferSingle([
            'out_biz_no' => 'BIZ_E2E',
            'amount'     => 50,
            'recipient'  => ['type' => 'openid', 'account' => 'OPEN_E2E', 'name' => '李四'],
        ]);

        self::assertSame('SUCCESS', $result['return_code']);
    }

    private function buildUnion(): Union
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
            'bits'             => 2048,
        ]);
        \assert($res !== false);
        openssl_pkey_export($res, $key);
        $keyFile = tempnam(sys_get_temp_dir(), 'wxkey') . '.pem';
        file_put_contents($keyFile, $key);

        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id'        => 'wx_app',
                    'secret'        => 'wechat-secret',
                    'mch_id'        => 'wechat_mch',
                    'mch_serial_no' => 'test_serial_no',
                    'key'           => self::API_KEY,
                    'key_path'      => $keyFile,
                    'cache'         => new ArrayCache(),
                ],
            ],
            new FakeHttpClient(),
        );

        return $kernel->union();
    }
}
