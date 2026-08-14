<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Union\Channel;
use PHPUnit\Framework\TestCase;

/**
 * Channel 能力发现元数据测试
 *
 * 验证 features() / supports() / providerKey() 如实反映当前适配器覆盖：
 * 微信生态各端（公众号 / 小程序 / App / H5 / PC）均已支持支付（统一 V3）。
 */
final class ChannelFeatureTest extends TestCase
{
    public function testWechatMiniSupportsAllCoreFeatures(): void
    {
        self::assertTrue(Channel::WechatMini->supports(ChannelFeature::Login));
        self::assertTrue(Channel::WechatMini->supports(ChannelFeature::Pay));
        self::assertTrue(Channel::WechatMini->supports(ChannelFeature::Notify));
        self::assertTrue(Channel::WechatMini->supports(ChannelFeature::User));
        self::assertTrue(Channel::WechatMini->supports(ChannelFeature::Decrypt));
    }

    public function testWechatH5SupportsPayButNotDecrypt(): void
    {
        self::assertTrue(Channel::WechatH5->supports(ChannelFeature::Login));
        self::assertTrue(Channel::WechatH5->supports(ChannelFeature::Pay));
        self::assertFalse(Channel::WechatH5->supports(ChannelFeature::Decrypt));
    }

    public function testWechatPcSupportsPay(): void
    {
        self::assertTrue(Channel::WechatPc->supports(ChannelFeature::Pay));
        self::assertTrue(Channel::WechatPc->supports(ChannelFeature::Notify));
    }

    public function testWechatOpenHasNoIndependentPay(): void
    {
        self::assertFalse(Channel::WechatOpen->supports(ChannelFeature::Pay));
        self::assertTrue(Channel::WechatOpen->supports(ChannelFeature::Login));
    }

    public function testDingtalkLarkSupportLoginAndUserOnly(): void
    {
        foreach ([Channel::Dingtalk, Channel::Lark] as $ch) {
            self::assertTrue($ch->supports(ChannelFeature::Login));
            self::assertTrue($ch->supports(ChannelFeature::User));
            self::assertFalse($ch->supports(ChannelFeature::Pay));
            self::assertFalse($ch->supports(ChannelFeature::Notify));
        }
    }

    public function testProviderKeyMapping(): void
    {
        self::assertSame('wechat', Channel::WechatMini->providerKey());
        self::assertSame('wechat', Channel::WechatH5->providerKey());
        self::assertSame('wechat_open', Channel::WechatApp->providerKey());
        self::assertSame('wechat_open', Channel::WechatOpen->providerKey());
        self::assertSame('wechat_work', Channel::WechatWork->providerKey());
        self::assertSame('alipay', Channel::AlipayMini->providerKey());
        self::assertSame('douyin', Channel::DouyinMini->providerKey());
        self::assertSame('baidu', Channel::BaiduMini->providerKey());
        self::assertSame('qq', Channel::Qq->providerKey());
        self::assertSame('dingtalk', Channel::Dingtalk->providerKey());
        self::assertSame('lark', Channel::Lark->providerKey());
    }

    public function testFeatureLabels(): void
    {
        self::assertSame('支付', ChannelFeature::Pay->label());
        self::assertSame('登录', ChannelFeature::Login->label());
    }
}
