<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * UnionUser::fromDecryptedUserInfo 工厂测试
 *
 * 验证「加密用户资料解密结果」能收敛为与登录 / profile 链路完全相同的 UnionUser 对象，
 * 且 gender 仅透传（不枚举映射）、空串归一为 null、原始 raw 完整保留。
 */
class UnionUserFromDecryptedTest extends TestCase
{
    public function testBuildsUnionUserFromEncryptedUserInfo(): void
    {
        $info = [
            'nickName'  => 'TestUser',
            'avatarUrl' => 'https://example.com/a.png',
            'gender'    => 1,
            'city'      => 'Shenzhen',
            'province'  => 'Guangdong',
            'country'   => 'China',
            'language'  => 'zh_CN',
            'watermark' => ['appid' => 'wxapp0000000000', 'timestamp' => 1495788248],
        ];

        $user = UnionUser::fromDecryptedUserInfo(Channel::WechatMini, $info, 'openid-1', 'union-1');

        self::assertInstanceOf(UnionUser::class, $user);
        self::assertSame('openid-1', $user->openId);
        self::assertSame('union-1', $user->unionId);
        self::assertSame(Channel::WechatMini, $user->channel);
        self::assertSame('TestUser', $user->nickname);
        self::assertSame('https://example.com/a.png', $user->avatar);
        self::assertSame('Shenzhen', $user->city);
        self::assertSame('Guangdong', $user->province);
        self::assertSame('China', $user->country);
        // gender 透传并 string 化（契合 ?string），不映射为 male/female
        self::assertSame('1', $user->gender);
        // 原始 raw 完整保留
        self::assertSame('TestUser', $user->raw['nickName']);
        self::assertSame('wxapp0000000000', $user->raw['watermark']['appid']);
    }

    public function testGenderStringPassedThrough(): void
    {
        $user = UnionUser::fromDecryptedUserInfo(
            Channel::DouyinMini,
            ['nickName' => 'U', 'gender' => '男'],
        );

        self::assertSame('男', $user->gender);
    }

    public function testMissingFieldsNormalizedToNull(): void
    {
        $user = UnionUser::fromDecryptedUserInfo(Channel::BaiduMini, []);

        self::assertSame('', $user->openId);
        self::assertSame('', $user->unionId);
        self::assertNull($user->nickname);
        self::assertNull($user->avatar);
        self::assertNull($user->gender);
        self::assertNull($user->city);
        self::assertNull($user->province);
        self::assertNull($user->country);
    }

    public function testEmptyGenderStringNormalizedToNull(): void
    {
        $user = UnionUser::fromDecryptedUserInfo(Channel::Lark, ['gender' => '']);

        self::assertNull($user->gender);
    }

    public function testCanonicalKeysAccepted(): void
    {
        // 直接传入已归一化的 canonical 键，同样有效
        $user = UnionUser::fromDecryptedUserInfo(
            Channel::WechatWork,
            ['nickname' => 'W', 'avatar' => 'https://x/y.png'],
        );

        self::assertSame('W', $user->nickname);
        self::assertSame('https://x/y.png', $user->avatar);
    }
}
