<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Session;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Session\ArrayCache;
use Kode\MiniApp\Session\CacheSessionStorage;
use Kode\MiniApp\Session\Session;
use Kode\MiniApp\Session\SessionManager;
use Kode\MiniApp\Session\SessionPolicy;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\Contracts\UserAdapter;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionUser;
use PHPUnit\Framework\TestCase;

/**
 * PlatformUnion 与 SessionManager 集成测试
 */
class PlatformUnionSessionTest extends TestCase
{
    private function kernelWithSession(SessionPolicy $policy = SessionPolicy::Multi): Kernel
    {
        $kernel = new Kernel([
            'wechat' => [
                'app_id'     => 'wxapp0000000000',
                'app_secret' => 'app-secret',
            ],
            'alipay' => [
                'app_id' => '2024...',
            ],
        ]);
        $kernel->union();
        $manager = new SessionManager(new CacheSessionStorage(new ArrayCache()), $policy);
        $kernel->union()->withSession($manager);
        return $kernel;
    }

    public function testWithSessionPropagatesToPlatforms(): void
    {
        $kernel = $this->kernelWithSession();
        $wechat = $kernel->union()->wechat();
        $alipay = $kernel->union()->alipay();

        self::assertNotNull($wechat->sessionManager());
        self::assertNotNull($alipay->sessionManager());
        self::assertSame(
            $kernel->union()->sessionManager(),
            $wechat->sessionManager()
        );
    }

    public function testCreateSessionExplicitly(): void
    {
        $kernel = $this->kernelWithSession(SessionPolicy::SingleAll);
        $user = UnionUser::fromRaw(
            channel: Channel::WechatMini,
            openId:  'open_001',
            unionId: 'union_001',
        );

        $session = $kernel->union()->wechat()->createSession(
            $user,
            scene:    'mini',
            client:   'ios',
            clientId: 'device_001',
            ip:       '192.168.1.1',
            userAgent:'iOS',
        );

        self::assertInstanceOf(Session::class, $session);
        self::assertSame('union_001', $session->unionId);
        self::assertSame('mini', $session->scene);
        self::assertSame('ios', $session->client);
        self::assertSame('device_001', $session->clientId);
    }

    public function testCreateSessionReturnsNullWhenManagerNotSet(): void
    {
        $kernel = new Kernel(['wechat' => ['app_id' => 'wx', 'app_secret' => 's']]);
        $kernel->union();
        $user = UnionUser::fromRaw(
            channel: Channel::WechatMini,
            openId:  'open_001',
            unionId: 'union_001',
        );
        // 没有 withSession - 返回 null
        $session = $kernel->union()->wechat()->createSession($user, 'mini');
        self::assertNull($session);
    }

    public function testSingleAllEnforcedAcrossPlatforms(): void
    {
        $kernel = $this->kernelWithSession(SessionPolicy::SingleAll);

        $user1 = UnionUser::fromRaw(
            channel: Channel::WechatMini,
            openId:  'open_001',
            unionId: 'union_001',
        );
        $s1 = $kernel->union()->wechat()->createSession($user1, 'mini', 'ios', 'd1');
        self::assertNotNull($s1);

        // 同一 unionId 在支付宝登录 - s1 会被踢
        $user2 = UnionUser::fromRaw(
            channel: Channel::AlipayMini,
            openId:  'alipay_open_001',
            unionId: 'union_001',
        );
        $s2 = $kernel->union()->alipay()->createSession($user2, 'mini', 'ios', 'd2');
        self::assertNotNull($s2);

        $manager = $kernel->union()->sessionManager();
        self::assertNotNull($manager);
        self::assertNull($manager->get($s1->id));
        self::assertNotNull($manager->get($s2->id));
    }

    public function testSessionManagerRespectsPolicyChange(): void
    {
        $storage = new CacheSessionStorage(new ArrayCache());
        $manager = new SessionManager($storage, SessionPolicy::Multi);

        $kernel = new Kernel(['wechat' => ['app_id' => 'wx', 'app_secret' => 's']]);
        $kernel->union();
        $kernel->union()->withSession($manager);

        self::assertSame(SessionPolicy::Multi, $manager->policy());

        $manager2 = $manager->withPolicy(SessionPolicy::SingleAll);
        self::assertSame(SessionPolicy::Multi, $manager->policy());
        self::assertSame(SessionPolicy::SingleAll, $manager2->policy());
    }
}
