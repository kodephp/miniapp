<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionUser;
use PHPUnit\Framework\TestCase;

class UnionTest extends TestCase
{
    public function testUnionEntrypointExists(): void
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
        ]);

        $union = $kernel->union();
        self::assertInstanceOf(Union::class, $union);
    }

    public function testMissingCodeThrows(): void
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('缺少必填参数: code');
        $kernel->union()->authenticate(Channel::WechatMini, []);
    }

    public function testEmptyCodeThrows(): void
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $kernel->union()->authenticate(Channel::WechatMini, ['code' => '']);
    }

    public function testLoginViaStringChannel(): void
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
        ]);

        // 自定义一个不需要真实 HTTP 的 adapter
        $adapter = new class implements LoginAdapter {
            public function channel(): Channel { return Channel::WechatMini; }
            public function authenticate(array $payload): UnionUser
            {
                return UnionUser::fromRaw(
                    channel: Channel::WechatMini,
                    openId:  $payload['openid'] ?? 'fake_openid',
                    unionId: $payload['unionid'] ?? 'fake_unionid',
                );
            }
        };

        $union = $kernel->union();
        $union->registerLoginAdapter($adapter);
        $user = $union->login('wechat_mini', ['openid' => 'TEST_OPEN_ID', 'unionid' => 'TEST_UNION_ID']);
        self::assertSame(Channel::WechatMini, $user->channel);
        self::assertSame('TEST_OPEN_ID', $user->openId);
        self::assertSame('TEST_UNION_ID', $user->unionId);
    }

    public function testLoginWithStringChannelFallsBackToDefault(): void
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
        ]);

        $adapter = new class implements LoginAdapter {
            public function channel(): Channel { return Channel::WechatMini; }
            public function authenticate(array $payload): UnionUser
            {
                return new UnionUser(
                    unionId: 'mock_union',
                    openId:  'mock_open',
                    channel: Channel::WechatMini,
                );
            }
        };

        $kernel->union()->registerLoginAdapter($adapter);
        $user = $kernel->union()->login('wechat_mini', []);
        self::assertSame('mock_open', $user->openId);
    }

    public function testUnsupportedPayChannelThrows(): void
    {
        $kernel = new Kernel([]);

        $this->expectException(\InvalidArgumentException::class);
        $kernel->union()->pay(Channel::Lark);
    }

    public function testRegisterCustomLoginAdapter(): void
    {
        $kernel = new Kernel([]);
        $union  = $kernel->union();

        $called  = false;
        $adapter = new class($called) implements LoginAdapter {
            public function __construct(public bool &$called) {}
            public function channel(): Channel { return Channel::BaiduMini; }
            public function authenticate(array $payload): UnionUser
            {
                $this->called = true;
                return UnionUser::fromRaw(
                    channel: Channel::BaiduMini,
                    openId:  'mock_open',
                );
            }
        };

        $union->registerLoginAdapter($adapter);
        $union->authenticate(Channel::BaiduMini, ['code' => 'X']);
        self::assertTrue($adapter->called);
    }

    public function testProfileUsesCustomAdapter(): void
    {
        $kernel = new Kernel([]);
        $union  = $kernel->union();

        $adapter = new class implements UserAdapter {
            public function channel(): Channel { return Channel::WechatMini; }
            public function profile(string $openId, array $payload = []): UnionUser
            {
                return UnionUser::fromRaw(
                    channel: Channel::WechatMini,
                    openId:  $openId,
                    raw:     ['nickname' => 'mock_' . $openId],
                );
            }
        };

        $union->registerUserAdapter($adapter);
        $user = $union->profile(Channel::WechatMini, 'open_test');
        self::assertSame('mock_open_test', $user->nickname);
    }
}
