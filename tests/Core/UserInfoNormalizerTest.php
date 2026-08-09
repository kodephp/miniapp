<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Core\UserInfoNormalizer;
use Kode\MiniApp\Tests\TestCase;

/**
 * 用户资料输出归一化测试
 *
 * 验证：兼容微信 getUserInfo 的 nickName / avatarUrl 等字段被归一化为稳定的
 * snake_case canonical 键（nickname / avatar / gender / city / province / country / language），
 * 缺失字段填空串、gender 缺失为 null、绝不抛异常。
 */
final class UserInfoNormalizerTest extends TestCase
{
    public function testNormalizesWechatStyleFields(): void
    {
        $raw = [
            'nickName'  => 'TestUser',
            'avatarUrl' => 'https://example.com/a.png',
            'gender'    => 1,
            'city'      => 'Guangzhou',
            'province'  => 'Guangdong',
            'country'   => 'CN',
            'language'  => 'zh_CN',
            'watermark' => ['appid' => 'wx123', 'timestamp' => 1495788248],
        ];

        $info = UserInfoNormalizer::normalize($raw);

        self::assertSame('TestUser', $info['nickname']);
        self::assertSame('https://example.com/a.png', $info['avatar']);
        self::assertSame(1, $info['gender']);
        self::assertSame('Guangzhou', $info['city']);
        self::assertSame('Guangdong', $info['province']);
        self::assertSame('CN', $info['country']);
        self::assertSame('zh_CN', $info['language']);
        // 原始字段不应出现在归一化结果中（仅 canonical 键），watermark 由调用方 array_merge 保留
        self::assertArrayNotHasKey('nickName', $info);
        self::assertArrayNotHasKey('watermark', $info);
    }

    public function testAcceptsSnakeCaseKeys(): void
    {
        $raw = ['nickname' => 'S', 'avatar' => 'A', 'gender' => 'm'];

        $info = UserInfoNormalizer::normalize($raw);

        self::assertSame('S', $info['nickname']);
        self::assertSame('A', $info['avatar']);
        self::assertSame('m', $info['gender']);
    }

    public function testMissingFieldsFillEmptyStringAndNullGender(): void
    {
        $info = UserInfoNormalizer::normalize([]);

        self::assertSame('', $info['nickname']);
        self::assertSame('', $info['avatar']);
        self::assertSame('', $info['city']);
        self::assertSame('', $info['province']);
        self::assertSame('', $info['country']);
        self::assertSame('', $info['language']);
        self::assertNull($info['gender']);
    }

    public function testGenderValuePassedThroughUnchanged(): void
    {
        // gender 仅透传，不做枚举映射（避免臆测各端编码）
        $info = UserInfoNormalizer::normalize(['gender' => 'female']);
        self::assertSame('female', $info['gender']);
    }
}
