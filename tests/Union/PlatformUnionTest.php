<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\Platforms\AlipayUnion;
use Kode\MiniApp\Union\Platforms\BaiduUnion;
use Kode\MiniApp\Union\Platforms\DingtalkUnion;
use Kode\MiniApp\Union\Platforms\DouyinUnion;
use Kode\MiniApp\Union\Platforms\LarkUnion;
use Kode\MiniApp\Union\Platforms\QqUnion;
use Kode\MiniApp\Union\Platforms\WechatOpenPlatformUnion;
use Kode\MiniApp\Union\Platforms\WechatUnion;
use Kode\MiniApp\Union\Platforms\WechatWorkUnion;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionUser;
use PHPUnit\Framework\TestCase;

/**
 * 平台聚合类（PlatformUnion）测试
 */
class PlatformUnionTest extends TestCase
{
    private function kernel(): Kernel
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
            'wechat_open' => [
                'component_appid'  => 'wxcomp0000000000',
                'component_secret' => 'comp-secret',
                'token'            => 'token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
            'alipay' => [
                'app_id' => '2024...',
            ],
            'douyin' => [
                'app_id' => 'tt...',
                'secret' => 'douyin-secret',
            ],
            'baidu' => [
                'app_id' => 'baidu-app',
                'secret' => 'baidu-secret',
            ],
            'qq' => [
                'app_id' => 'qq-app',
                'secret' => 'qq-secret',
            ],
            'wechat_work' => [
                'corp_id'  => 'ww...',
                'secret'   => 'work-secret',
                'agent_id' => '1000002',
            ],
            'dingtalk' => [
                'app_key'    => 'ding-key',
                'app_secret' => 'ding-secret',
            ],
            'lark' => [
                'app_id' => 'cli_...',
                'secret' => 'lark-secret',
            ],
        ]);
        // 触发 union 初始化
        $kernel->union();
        return $kernel;
    }

    public function testWechatPlatformClass(): void
    {
        $kernel = $this->kernel();
        $wechat = $kernel->union()->wechat();
        self::assertInstanceOf(WechatUnion::class, $wechat);
        self::assertSame('wechat', $wechat->platform());
    }

    public function testWechatOpenPlatformClass(): void
    {
        $kernel = $this->kernel();
        $open = $kernel->union()->wechatOpen();
        self::assertInstanceOf(WechatOpenPlatformUnion::class, $open);
        self::assertSame('wechat_open', $open->platform());
    }

    public function testAlipayPlatformClass(): void
    {
        $kernel = $this->kernel();
        $alipay = $kernel->union()->alipay();
        self::assertInstanceOf(AlipayUnion::class, $alipay);
        self::assertSame('alipay', $alipay->platform());
    }

    public function testDouyinPlatformClass(): void
    {
        $kernel = $this->kernel();
        $douyin = $kernel->union()->douyin();
        self::assertInstanceOf(DouyinUnion::class, $douyin);
        self::assertSame('douyin', $douyin->platform());
    }

    public function testBaiduPlatformClass(): void
    {
        $kernel = $this->kernel();
        $baidu = $kernel->union()->baidu();
        self::assertInstanceOf(BaiduUnion::class, $baidu);
        self::assertSame('baidu', $baidu->platform());
    }

    public function testQqPlatformClass(): void
    {
        $kernel = $this->kernel();
        $qq = $kernel->union()->qq();
        self::assertInstanceOf(QqUnion::class, $qq);
        self::assertSame('qq', $qq->platform());
    }

    public function testWechatWorkPlatformClass(): void
    {
        $kernel = $this->kernel();
        $work = $kernel->union()->wechatWork();
        self::assertInstanceOf(WechatWorkUnion::class, $work);
        self::assertSame('wechat_work', $work->platform());
    }

    public function testWorkAliasMatchesWechatWork(): void
    {
        $kernel = $this->kernel();
        self::assertSame(
            $kernel->union()->wechatWork(),
            $kernel->union()->work()
        );
    }

    public function testDingtalkPlatformClass(): void
    {
        $kernel = $this->kernel();
        $ding = $kernel->union()->dingtalk();
        self::assertInstanceOf(DingtalkUnion::class, $ding);
        self::assertSame('dingtalk', $ding->platform());
    }

    public function testLarkPlatformClass(): void
    {
        $kernel = $this->kernel();
        $lark = $kernel->union()->lark();
        self::assertInstanceOf(LarkUnion::class, $lark);
        self::assertSame('lark', $lark->platform());
    }

    public function testStaticAccessViaUnionClass(): void
    {
        $this->kernel();
        $wechat = Union::wechat();
        self::assertInstanceOf(WechatUnion::class, $wechat);
    }

    public function testStaticAccessAlipay(): void
    {
        $this->kernel();
        $alipay = Union::alipay();
        self::assertInstanceOf(AlipayUnion::class, $alipay);
    }

    public function testStaticAccessWithoutKernelThrows(): void
    {
        // 重置静态 kernel
        $reflection = new \ReflectionClass(Union::class);
        $property = $reflection->getProperty('globalKernel');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        Union::wechat();
    }

    public function testCustomLoginAdapterViaPlatformUnion(): void
    {
        $kernel = $this->kernel();
        $adapter = new class implements LoginAdapter {
            public function channel(): Channel { return Channel::WechatMini; }
            public function authenticate(array $payload): UnionUser
            {
                return UnionUser::fromRaw(
                    channel: Channel::WechatMini,
                    openId:  'static_open',
                    unionId: 'static_union',
                );
            }
        };

        $kernel->union()->registerLoginAdapter($adapter);
        $user = $kernel->union()->wechat()->login(['code' => 'X']);
        self::assertSame('static_open', $user->openId);
        self::assertSame('static_union', $user->unionId);
    }

    public function testCustomUserAdapterViaPlatformUnion(): void
    {
        $kernel = $this->kernel();
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

        $kernel->union()->registerUserAdapter($adapter);
        $user = $kernel->union()->wechat()->user('test_open');
        self::assertSame('mock_test_open', $user->nickname);
    }

    public function testPayChannelFallback(): void
    {
        $kernel = $this->kernel();
        $pay = $kernel->union()->wechat()->pay();
        self::assertInstanceOf(\Kode\MiniApp\Union\Contracts\PayAdapter::class, $pay);
    }

    public function testNotifyChannelFallback(): void
    {
        $kernel = $this->kernel();
        $notify = $kernel->union()->wechat()->notify();
        self::assertInstanceOf(\Kode\MiniApp\Union\Contracts\NotifyAdapter::class, $notify);
    }

    public function testUnsupportedSceneThrows(): void
    {
        $kernel = $this->kernel();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不支持场景 [unknown_scene]');
        $kernel->union()->wechat()->login(['code' => 'X'], 'unknown_scene');
    }
}
