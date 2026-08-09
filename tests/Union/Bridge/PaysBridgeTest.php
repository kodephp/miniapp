<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Contracts\KernelInterface;
use Kode\MiniApp\Kernel as KernelClass;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
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
        ]);
    }

    public function testAvailableReflectsPaysPresence(): void
    {
        // 本环境未安装 kode/pays，available() 应为 false
        self::assertFalse(PaysBridge::available());
    }

    public function testUnifiedOrderThrowsWhenPaysMissing(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, static fn () => [
            'app_id' => 'wx', 'mch_id' => 'm', 'api_key' => 'k',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('composer require kode/pays');
        $adapter->unifiedOrder(['out_trade_no' => 'T1']);
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

    public function testKernelResolverUnsupportedChannelThrows(): void
    {
        $resolver = PaysBridge::kernelResolver($this->kernel());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('暂未覆盖渠道');
        $resolver(Channel::DouyinMini);
    }
}
