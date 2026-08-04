<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Session;

use Kode\MiniApp\Session\ArrayCache;
use Kode\MiniApp\Session\CacheSessionStorage;
use Kode\MiniApp\Session\Session;
use Kode\MiniApp\Session\SessionManager;
use Kode\MiniApp\Session\SessionPolicy;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;
use PHPUnit\Framework\TestCase;

/**
 * SessionManager 测试
 */
class SessionManagerTest extends TestCase
{
    private function manager(SessionPolicy $policy = SessionPolicy::Multi): SessionManager
    {
        $storage = new CacheSessionStorage(new ArrayCache());
        return new SessionManager($storage, $policy);
    }

    private function user(
        string $openId = 'open_001',
        string $unionId = 'union_001',
        Channel $channel = Channel::WechatMini,
    ): UnionUser {
        return UnionUser::fromRaw(
            channel: $channel,
            openId:  $openId,
            unionId: $unionId,
        );
    }

    public function testCreateSession(): void
    {
        $m = $this->manager();
        $session = $m->create($this->user(), 'mini', 'ios', 'device_001');

        self::assertNotEmpty($session->id);
        self::assertSame('union_001', $session->unionId);
        self::assertSame('open_001', $session->openId);
        self::assertSame('mini', $session->scene);
        self::assertSame('ios', $session->client);
        self::assertSame('device_001', $session->clientId);
        self::assertSame(Channel::WechatMini, $session->channel);
        self::assertGreaterThan(time(), $session->expiresAt);
    }

    public function testGetSession(): void
    {
        $m = $this->manager();
        $session = $m->create($this->user(), 'mini', 'ios', 'd1');

        $found = $m->get($session->id);
        self::assertNotNull($found);
        self::assertSame($session->id, $found->id);
    }

    public function testGetNonExistentSession(): void
    {
        $m = $this->manager();
        self::assertNull($m->get('not_exist'));
    }

    public function testDestroySession(): void
    {
        $m = $this->manager();
        $session = $m->create($this->user(), 'mini', 'ios', 'd1');
        $m->destroy($session->id);
        self::assertNull($m->get($session->id));
    }

    public function testPolicyMultiAllowsConcurrentSessions(): void
    {
        $m = $this->manager(SessionPolicy::Multi);
        $s1 = $m->create($this->user(), 'mini', 'ios', 'd1');
        $s2 = $m->create($this->user(), 'mp', 'web', '');
        $s3 = $m->create($this->user(), 'pc', 'pc', '');

        self::assertNotNull($m->get($s1->id));
        self::assertNotNull($m->get($s2->id));
        self::assertNotNull($m->get($s3->id));
    }

    public function testPolicySingleEndEvictsByClient(): void
    {
        $m = $this->manager(SessionPolicy::SingleEnd);
        $user1 = $this->user('open_A', 'union_A');
        $user2 = $this->user('open_B', 'union_B');

        // 不同账号在同一设备登录 - 后者踢前者
        $s1 = $m->create($user1, 'mini', 'ios', 'device_001');
        $s2 = $m->create($user2, 'mini', 'ios', 'device_001');

        // s1, s2 是 Session 实例，其 id 一定是字符串，所以不需要 assertNotNull
        self::assertNotEmpty($s1->id);
        self::assertNotEmpty($s2->id);
        self::assertNull($m->get($s1->id));  // s1 已被踢
        self::assertNotNull($m->get($s2->id));
    }

    public function testPolicySingleEndAllowsDifferentDevices(): void
    {
        $m = $this->manager(SessionPolicy::SingleEnd);
        $user1 = $this->user('open_A', 'union_A');
        $user2 = $this->user('open_B', 'union_B');

        // 不同账号在不同设备 - 不冲突
        $s1 = $m->create($user1, 'mini', 'ios', 'device_A');
        $s2 = $m->create($user2, 'mini', 'ios', 'device_B');

        self::assertNotNull($m->get($s1->id));
        self::assertNotNull($m->get($s2->id));
    }

    public function testPolicySingleUserEvictsSameScene(): void
    {
        // SingleUser = "单账号单端"：同账号同端口重复登录时，新登录踢旧登录
        // 不同端口之间不互踢（这是和 SingleAll 的区别）
        $m = $this->manager(SessionPolicy::SingleUser);
        $user = $this->user('open_001', 'union_001');

        // 同 scene 重复登录 - 第一次被踢
        $s1 = $m->create($user, 'mini', 'ios', 'd1');
        $s2 = $m->create($user, 'mini', 'android', 'd2');

        self::assertNull($m->get($s1->id));
        self::assertNotNull($m->get($s2->id));
    }

    public function testPolicySingleUserAllowsDifferentScenes(): void
    {
        // SingleUser 允许同一账号在不同端口登录
        $m = $this->manager(SessionPolicy::SingleUser);
        $user = $this->user('open_001', 'union_001');

        $s1 = $m->create($user, 'mini', 'ios', 'd1');
        $s2 = $m->create($user, 'mp', 'web', '');

        self::assertNotNull($m->get($s1->id));
        self::assertNotNull($m->get($s2->id));
    }

    public function testPolicySingleAllEvictsEverything(): void
    {
        $m = $this->manager(SessionPolicy::SingleAll);
        $user = $this->user('open_001', 'union_001');

        // 同一用户多端登录
        $s1 = $m->create($user, 'mini', 'ios', 'd1');
        $s2 = $m->create($user, 'mp', 'web', '');
        $s3 = $m->create($user, 'pc', 'pc', '');

        // 任何新登录都会踢掉之前的
        self::assertNull($m->get($s1->id));
        self::assertNull($m->get($s2->id));
        self::assertNotNull($m->get($s3->id));
    }

    public function testListByUnionId(): void
    {
        $m = $this->manager(SessionPolicy::Multi);
        $user = $this->user('open_001', 'union_001');

        $s1 = $m->create($user, 'mini', 'ios', 'd1');
        $s2 = $m->create($user, 'mp', 'web', '');
        $m->create($this->user('open_999', 'union_999'), 'mini', 'ios', 'd2');

        $sessions = $m->listByUnionId('union_001');
        $ids = array_map(fn($s) => $s->id, $sessions);
        self::assertContains($s1->id, $ids);
        self::assertContains($s2->id, $ids);
        self::assertCount(2, $sessions);
    }

    public function testDestroyAll(): void
    {
        $m = $this->manager(SessionPolicy::Multi);
        $user = $this->user('open_001', 'union_001');

        $s1 = $m->create($user, 'mini', 'ios', 'd1');
        $s2 = $m->create($user, 'mp', 'web', '');

        $count = $m->destroyAll('union_001');
        self::assertSame(2, $count);
        self::assertNull($m->get($s1->id));
        self::assertNull($m->get($s2->id));
    }

    public function testDestroyByClient(): void
    {
        $m = $this->manager(SessionPolicy::Multi);
        $user = $this->user('open_001', 'union_001');

        $m->create($user, 'mini', 'ios', 'd1');
        $m->create($user, 'mp', 'web', '');

        $count = $m->destroyByClient('union_001', 'ios', 'd1');
        self::assertSame(1, $count);
        self::assertCount(1, $m->listByUnionId('union_001'));
    }

    public function testTouchExtendsSession(): void
    {
        // 创建一个即将过期的 session
        $storage = new CacheSessionStorage(new ArrayCache());
        $m = new SessionManager($storage, SessionPolicy::Multi, 100);  // 100 秒 ttl
        $session = $m->create($this->user(), 'mini', 'ios', 'd1');
        $oldExpires = $session->expiresAt;

        // 续期到 7200 秒
        $new = $m->touch($session->id, 7200);
        self::assertNotNull($new);
        self::assertGreaterThan($oldExpires, $new->expiresAt);
        // 大约 7200 秒后过期
        self::assertGreaterThanOrEqual(7190, $new->ttl());
    }

    public function testSessionSerializesAndRestores(): void
    {
        $original = new Session(
            id:        'sess_001',
            unionId:   'u_001',
            openId:    'o_001',
            channel:   Channel::WechatMini,
            scene:     'mini',
            client:    'ios',
            clientId:  'd1',
            ip:        '127.0.0.1',
            userAgent: 'UA-Test',
            createdAt: 1700000000,
            expiresAt: 1700086400,
            payload:   ['role' => 'admin'],
        );
        $arr = $original->toArray();
        $restored = Session::fromArray($arr);

        self::assertSame($original->id, $restored->id);
        self::assertSame($original->unionId, $restored->unionId);
        self::assertSame($original->channel, $restored->channel);
        self::assertSame($original->payload, $restored->payload);
    }

    public function testIsExpired(): void
    {
        $session = new Session(
            id:        's1',
            unionId:   'u1',
            openId:    'o1',
            channel:   Channel::WechatMini,
            scene:     'mini',
            client:    'ios',
            clientId:  'd1',
            ip:        '',
            userAgent: '',
            createdAt: time() - 100,
            expiresAt: time() - 1,
        );
        self::assertTrue($session->isExpired());

        $future = new Session(
            id:        's2',
            unionId:   'u1',
            openId:    'o1',
            channel:   Channel::WechatMini,
            scene:     'mini',
            client:    'ios',
            clientId:  'd1',
            ip:        '',
            userAgent: '',
            createdAt: time(),
            expiresAt: time() + 100,
        );
        self::assertFalse($future->isExpired());
    }

    public function testPolicyLabels(): void
    {
        self::assertSame('多端可同时登录', SessionPolicy::Multi->label());
        self::assertStringContainsString('单端单账号', SessionPolicy::SingleEnd->label());
        self::assertStringContainsString('单账号单端', SessionPolicy::SingleUser->label());
        self::assertStringContainsString('单账号全端', SessionPolicy::SingleAll->label());
    }

    public function testWithPolicyIsImmutable(): void
    {
        $m1 = $this->manager(SessionPolicy::Multi);
        $m2 = $m1->withPolicy(SessionPolicy::SingleAll);

        self::assertSame(SessionPolicy::Multi, $m1->policy());
        self::assertSame(SessionPolicy::SingleAll, $m2->policy());
    }

    public function testWechatVideoAppScenario(): void
    {
        // 模拟"优酷/腾讯视频"场景：单账号全端（防止账号共享）
        $m = $this->manager(SessionPolicy::SingleAll);
        $user = $this->user('video_user_001', 'video_union_001');

        // 用户 A 在 PC 登录
        $sessionPC = $m->create($user, 'pc', 'web', 'pc_browser_001', '192.168.1.1', 'Chrome');

        // 用户 A 在小程序登录 - 之前 PC 的 session 应被踢
        $sessionMini = $m->create($user, 'mini', 'ios', 'phone_001', '10.0.0.1', 'iOS');

        self::assertNull($m->get($sessionPC->id));
        self::assertNotNull($m->get($sessionMini->id));

        // 用户 A 试图在另一个设备登录 - 之前小程序的 session 也被踢
        $sessionAndroid = $m->create($user, 'mini', 'android', 'phone_002', '10.0.0.2', 'Android');
        self::assertNull($m->get($sessionMini->id));
        self::assertNotNull($m->get($sessionAndroid->id));

        // 最终该用户只能有一个活跃 session
        self::assertCount(1, $m->listByUnionId($user->unionId));
    }
}
