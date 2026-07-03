# SessionManager 多端登录约束

> **问题场景**：1 个用户可能从小程序、APP、PC、公众号等多个端口登录。
> 某些业务（优酷/腾讯视频/银行 App）需要约束登录端口，防止账号共享或设备被顶替。

## 与 `kode/jwt` 的关系（设计边界）

| 关注点 | 归属 |
|--------|------|
| Token 签发 / 验证 / 刷新 | `kode/jwt`（stateless） |
| Token 黑名单 / 撤销 | `kode/jwt`（通过 jti） |
| 登录约束 / 多端踢人 | **`kode/miniapp` SessionManager**（stateful） |
| 业务侧权限 / 角色 | 业务侧 |

**为什么 SessionManager 放在本包而不是 kode/jwt**：

- `kode/jwt` 是 stateless 的，token 自包含信息，不应该承担多端约束职责
- 多端约束需要 stateful 存储（按 unionId 查所有 session），与本包"统一账号体系"职责紧密耦合
- `kode/jwt` 只负责 token 层面，business logic 应该在业务侧

## 4 种登录约束策略

| 策略 | 含义 | 适用场景 |
|------|------|----------|
| `Multi` | 多端可同时登录（默认） | 通用应用 |
| `SingleEnd` | 单设备单账号（同设备只能登录 1 个账号） | 共享设备、家庭账户 |
| `SingleUser` | 单账号单端（同账号同端口重复登录踢旧） | 跨端允许，限同端重复 |
| `SingleAll` | 单账号全端（同账号只能登录 1 次） | 优酷、腾讯视频、银行 App |

### 策略对比

```
场景：用户在三个设备登录了同一账号
   - iPhone 小程序 (unionId=u001)
   - Android 小程序 (unionId=u001)
   - PC 公众号 (unionId=u001)

Multi:        全部允许              → 3 个 session 都有效
SingleEnd:    按设备隔离            → 3 个 session 都有效（不同设备），但同设备重复登录会踢
SingleUser:   按 unionId+scene 隔离  → 3 个都有效（小程序和公众号是不同 scene），同 scene 重复登录踢
SingleAll:    按 unionId 全局唯一   → 只有最后一个 session 有效（前面的都被踢）
```

## 快速开始

```php
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Session\SessionManager;
use Kode\MiniApp\Session\CacheSessionStorage;
use Kode\MiniApp\Session\SessionPolicy;
use Kode\MiniApp\Union\Union;

// 1. 初始化
$kernel = new Kernel(['wechat' => [...]]);
$kernel->union();

// 2. 接入 SessionManager
$session = new SessionManager(
    new CacheSessionStorage($redisCache),
    SessionPolicy::SingleAll,  // 强制单账号全端
    86400 * 30                 // 30 天过期
);
$kernel->union()->withSession($session);

// 3. 业务侧登录
$user = Union::wechat()->mini('JS_CODE');  // 同账号再登录会自动踢掉之前
```

## 业务侧与 kode/jwt 配合

```php
use Kode\MiniApp\Union\Union;
use Kode\Jwt\Jwt;

// 1. 登录并创建 session
$user = Union::wechat()->mini('JS_CODE');
$session = Union::wechat()->createSession($user, 'mini', 'ios', $deviceId);

// 2. 用 session.id 作为 JWT jti 签发
$jwt = new Jwt($secret, $algorithm);
$token = $jwt->issue([
    'jti' => $session->id,        // 用 session ID 作为 JWT ID
    'sub' => $user->unionId,
    'exp' => $session->expiresAt,
]);

// 3. 后续请求验证
$payload = $jwt->verify($token);
$session = $sessionManager->get($payload->jti);
if ($session === null) {
    throw new UnauthorizedException('会话已失效（被踢下线或过期）');
}

// 4. 主动踢人
$sessionManager->destroy($payload->jti);  // 当前 token 立刻失效
```

## API 详解

### SessionManager

```php
class SessionManager
{
    public function __construct(
        SessionStorageInterface $storage,
        SessionPolicy $policy = SessionPolicy::Multi,
        int $ttl = 86400 * 30
    );

    // 创建 session（自动应用策略踢掉冲突 session）
    public function create(
        UnionUser $user,
        string $scene,
        string $client = 'web',
        string $clientId = '',
        string $ip = '',
        string $userAgent = '',
        array $payload = [],
        ?int $ttl = null
    ): Session;

    // 查询
    public function get(string $sessionId): ?Session;
    public function listByUnionId(string $unionId): array;

    // 续期
    public function touch(string $sessionId, ?int $ttl = null): ?Session;

    // 销毁
    public function destroy(string $sessionId): void;
    public function destroyByClient(string $unionId, string $client, string $clientId = ''): int;
    public function destroyAll(string $unionId): int;

    // 策略（不可变）
    public function withPolicy(SessionPolicy $policy): self;
    public function policy(): SessionPolicy;
}
```

### Session

```php
final readonly class Session
{
    public string $id;         // session ID（= JWT jti）
    public string $unionId;    // 跨平台统一 ID
    public string $openId;     // 平台内 OpenID
    public Channel $channel;   // 渠道
    public string $scene;      // 端口（mini/mp/h5/pc/app）
    public string $client;     // 客户端类型
    public string $clientId;   // 设备指纹
    public string $ip;
    public string $userAgent;
    public int $createdAt;
    public int $expiresAt;
    public array $payload;

    public function isExpired(?int $now = null): bool;
    public function ttl(?int $now = null): int;
    public function toArray(): array;
    public static function fromArray(array $data): self;
}
```

## 自定义存储

`SessionManager` 底层使用 `SessionStorageInterface`，可注入任何实现：

```php
interface SessionStorageInterface
{
    public function write(string $sessionId, array $data, int $ttl): void;
    public function read(string $sessionId): ?array;
    public function delete(string $sessionId): void;
    public function findByIndex(string $indexKey): array;
    public function countByIndex(string $indexKey): int;
}
```

内置两种实现：

- [CacheSessionStorage](file:///Users/Zhuanz/Desktop/website/composer/miniapp/src/Session/CacheSessionStorage.php)：基于 PSR-16 Cache（Redis / Memcached / 文件）
- 自定义：业务侧可基于 MySQL 等实现 `SessionStorageInterface`

## 架构图

```
                业务侧
                  ↓
Union::wechat()->login($code)   ← 一行代码完成登录
                  ↓
PlatformUnion::login()
                  ↓
Union::authenticate(Channel::WechatMini, $payload)
                  ↓
MiniLoginAdapter::authenticate()  ← 调用微信 jscode2session
                  ↓
返回 UnionUser（含 unionId / openId）
                  ↓
PlatformUnion::autoCreateSession()  ← 自动调用（如有 SessionManager）
                  ↓
SessionManager::create($user, ...)
                  ↓
按策略踢掉冲突 session (evictConflicts)
                  ↓
写新 session 到 storage
                  ↓
返回 Session
```
