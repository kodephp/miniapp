<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Union\Channel;
use PHPUnit\Framework\TestCase;

class ChannelTest extends TestCase
{
    public function testAllChannelsHaveLabel(): void
    {
        foreach (Channel::cases() as $case) {
            self::assertNotSame('', $case->label(), "渠道 [{$case->name}] 必须有中文标签");
        }
    }

    public function testWechatEcosystem(): void
    {
        $wechat = [
            Channel::WechatMp,
            Channel::WechatMini,
            Channel::WechatH5,
            Channel::WechatPc,
            Channel::WechatApp,
            Channel::WechatOpen,
            Channel::WechatWork,
            Channel::Qq,
        ];
        foreach ($wechat as $ch) {
            self::assertTrue($ch->isWechatEcosystem(), "{$ch->name} 应属于微信生态");
        }

        $nonWechat = [Channel::AlipayMini, Channel::DouyinMini, Channel::Dingtalk, Channel::Lark];
        foreach ($nonWechat as $ch) {
            self::assertFalse($ch->isWechatEcosystem(), "{$ch->name} 不应属于微信生态");
        }
    }

    public function testValueUniqueness(): void
    {
        $values = array_map(fn(Channel $c) => $c->value, Channel::cases());
        self::assertCount(count($values), array_unique($values), '渠道值必须唯一');
    }
}
