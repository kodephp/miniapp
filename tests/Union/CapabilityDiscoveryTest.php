<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Union\CapabilityInfo;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;

/**
 * 能力发现端到端测试
 *
 * 验证 Union::capabilities() 能聚合「渠道能力 + 必填配置」，
 * 且如实反映覆盖情况（微信 H5 无 Pay、不要求 mch_id）。
 */
final class CapabilityDiscoveryTest extends TestCase
{
    private function union(): Union
    {
        $http = new FakeHttpClient();
        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id'        => 'wx',
                    'secret'        => 'sec',
                    'mch_id'        => 'mch',
                    'mch_serial_no' => 'serial',
                    'key_path'      => '/tmp/none.pem',
                    'cache'         => new ArrayCache(),
                ],
                'alipay' => [
                    'app_id'      => 'ali',
                    'private_key' => 'pk',
                    'public_key'  => 'pub',
                ],
            ],
            $http,
        );

        return $kernel->union();
    }

    public function testWechatMiniCapability(): void
    {
        $info = $this->union()->capabilities(Channel::WechatMini);

        self::assertInstanceOf(CapabilityInfo::class, $info);
        self::assertTrue($info->supports(ChannelFeature::Pay));
        self::assertContains('app_id', $info->requiredConfig);
        self::assertContains('mch_id', $info->requiredConfig);
        self::assertContains('key_path', $info->requiredConfig);
        self::assertContains('mch_serial_no', $info->requiredConfig);
    }

    public function testWechatH5HasPay(): void
    {
        $info = $this->union()->capabilities(Channel::WechatH5);

        self::assertTrue($info->supports(ChannelFeature::Pay));
        self::assertContains('mch_id', $info->requiredConfig);
    }

    public function testAlipayCapability(): void
    {
        $info = $this->union()->capabilities(Channel::AlipayMini);

        self::assertTrue($info->supports(ChannelFeature::Pay));
        self::assertContains('app_id', $info->requiredConfig);
        self::assertContains('private_key', $info->requiredConfig);
        self::assertContains('public_key', $info->requiredConfig);
    }

    public function testToArrayShape(): void
    {
        $arr = $this->union()->capabilities(Channel::WechatMini)->toArray();

        self::assertSame('wechat_mini', $arr['channel']);
        self::assertContains('pay', $arr['features']);
        self::assertArrayHasKey('required_config', $arr);
    }
}
