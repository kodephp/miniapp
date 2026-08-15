<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Kernel as KernelClass;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\Pays\Facade\Pay;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PaysBridge::class)]
#[CoversClass(PaysBridgePayAdapter::class)]
final class PaysBridgeTest extends TestCase
{
    private function kernel(): KernelInterface
    {
        return new KernelClass([
            'wechat' => [
                'app_id'      => 'wx1234567890abcdef',
                'app_secret'  => 'app-secret',
                'mch_id'      => 'mch001',
                'key'         => 'merchant-v2-key',
                'api_v3_key'  => 'v3-secret-key',
                'cert_path'   => '/certs/apiclient_cert.pem',
                'key_path'    => '/certs/apiclient_key.pem',
            ],
            'alipay' => [
                'app_id'      => '2024xxxxxxxxxxxx',
                'private_key' => '-----BEGIN RSA PRIVATE KEY-----\n...',
                'public_key'  => '-----BEGIN PUBLIC KEY-----\n...',
                'sandbox'     => true,
            ],
            'douyin' => [
                'app_id' => 'tt_app_123',
                'secret' => 'douyin-secret',
                'salt'   => 'douyin-salt',
            ],
            'qq' => [
                'app_id' => 'qq_app_123',
                'secret' => 'qq-secret',
            ],
        ]);
    }

    protected function setUp(): void
    {
        // 隔离 kode/pays 门面静态状态，避免跨测试污染
        Pay::setHttpClient(new FakePaysHttpClient());
        Pay::clearCache();
    }

    public function testAvailableReflectsPaysPresence(): void
    {
        // kode/pays 现已作为硬依赖安装进 vendor，available() 应为 true
        self::assertTrue(PaysBridge::available());
    }

    public function testInsufficientGatewayConfigThrowsViaRealPays(): void
    {
        // 2.0 起 pays 为唯一支付路径（真实安装）。配置缺失必填项时，
        // 真实微信网关在构造阶段即抛出清晰异常，证明走的是真实 pays 网关而非桩。
        $adapter = PaysBridge::adapter(Channel::WechatMini, static fn () => [
            'app_id' => 'wx',
        ]);

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('mch_id');
        $adapter->createOrder(['out_trade_no' => 'T1', 'total_fee' => 1, 'body' => 'x', 'trade_type' => 'JSAPI']);
    }

    public function testAdapterForKernelReturnsBridge(): void
    {
        $adapter = PaysBridge::adapterForKernel(Channel::WechatMini, $this->kernel());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);
        self::assertInstanceOf(PayAdapter::class, $adapter);
        self::assertSame(Channel::WechatMini, $adapter->channel());
    }

    public function testKernelResolverMapsWechatFields(): void
    {
        $resolver = PaysBridge::kernelResolver($this->kernel());
        $config   = $resolver(Channel::WechatMini);

        self::assertSame('wx1234567890abcdef', $config['app_id']);
        self::assertSame('mch001', $config['mch_id']);
        // miniapp 字段 key → kode/pays api_key
        self::assertSame('merchant-v2-key', $config['api_key']);
        self::assertSame('v3-secret-key', $config['api_v3_key']);
        self::assertSame('/certs/apiclient_cert.pem', $config['cert_path']);
        self::assertSame('/certs/apiclient_key.pem', $config['key_path']);
    }

    public function testKernelResolverMapsAlipayFields(): void
    {
        $resolver = PaysBridge::kernelResolver($this->kernel());
        $config   = $resolver(Channel::AlipayMini);

        self::assertSame('2024xxxxxxxxxxxx', $config['app_id']);
        self::assertSame('-----BEGIN RSA PRIVATE KEY-----\n...', $config['private_key']);
        self::assertSame('-----BEGIN PUBLIC KEY-----\n...', $config['public_key']);
        self::assertTrue($config['sandbox']);
    }

    public function testKernelResolverMapsDouyinFields(): void
    {
        $resolver = PaysBridge::kernelResolver($this->kernel());
        $config   = $resolver(Channel::DouyinMini);

        self::assertSame('tt_app_123', $config['app_id']);
        self::assertSame('douyin-secret', $config['secret']);
        self::assertSame('douyin-salt', $config['salt']);
    }

    public function testKernelResolverMapsQqFields(): void
    {
        $resolver = PaysBridge::kernelResolver($this->kernel());
        $config   = $resolver(Channel::Qq);

        self::assertSame('qq_app_123', $config['app_id']);
        self::assertSame('qq-secret', $config['secret']);
    }

    public function testKernelResolverUnsupportedChannelThrows(): void
    {
        $resolver = PaysBridge::kernelResolver($this->kernel());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('暂未覆盖渠道');
        $resolver(Channel::BaiduMini);
    }
}
