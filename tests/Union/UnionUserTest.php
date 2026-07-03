<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;
use PHPUnit\Framework\TestCase;

class UnionUserTest extends TestCase
{
    public function testDirectConstruction(): void
    {
        $user = new UnionUser(
            unionId:  'union_001',
            openId:   'open_001',
            channel:  Channel::WechatMini,
            nickname: '张三',
            avatar:   'https://example.com/avatar.png',
        );

        self::assertSame('union_001', $user->unionId);
        self::assertSame('open_001', $user->openId);
        self::assertSame(Channel::WechatMini, $user->channel);
        self::assertSame('张三', $user->nickname);
        self::assertTrue($user->hasUnionId());
    }

    public function testHasUnionId(): void
    {
        $user = new UnionUser(
            unionId: '',
            openId:  'open_001',
            channel: Channel::WechatMini,
        );
        self::assertFalse($user->hasUnionId());
    }

    public function testToArray(): void
    {
        $user = UnionUser::fromRaw(
            channel: Channel::AlipayMini,
            openId:  'alipay_open_001',
            unionId: '',
            raw:     [
                'nickname' => '支付宝昵称',
                'avatar'   => 'https://example.com/avatar.jpg',
                'gender'   => 'F',
                'city'     => '杭州',
            ],
        );

        $array = $user->toArray();
        self::assertSame('alipay_open_001', $array['open_id']);
        self::assertSame('alipay_mini', $array['channel']);
        self::assertSame('支付宝昵称', $array['nickname']);
        self::assertSame('https://example.com/avatar.jpg', $array['avatar']);
        self::assertSame('杭州', $array['city']);
    }

    public function testFromRawExtractsMultipleFields(): void
    {
        $user = UnionUser::fromRaw(
            channel: Channel::WechatMp,
            openId:  'open_xxx',
            raw:     [
                'nickname'  => '微信昵称',
                'headimgurl' => 'https://example.com/wx.png',
                'sex'       => 1,  // 1=male
                'country'   => '中国',
                'province'  => '广东',
                'city'      => '深圳',
            ],
        );

        self::assertSame('微信昵称', $user->nickname);
        self::assertSame('https://example.com/wx.png', $user->avatar);
        self::assertSame('male', $user->gender);
        self::assertSame('中国', $user->country);
        self::assertSame('广东', $user->province);
        self::assertSame('深圳', $user->city);
    }

    public function testFromRawHandlesEmptyValues(): void
    {
        $user = UnionUser::fromRaw(
            channel: Channel::WechatMini,
            openId:  'open_xxx',
        );

        self::assertNull($user->nickname);
        self::assertNull($user->avatar);
        self::assertNull($user->gender);
        self::assertSame('', $user->unionId);
        self::assertSame([], $user->raw);
    }
}
