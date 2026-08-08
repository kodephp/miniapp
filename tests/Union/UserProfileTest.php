<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 全平台用户资料端到端集成测试（抖音 / QQ / 百度 / 企业微信）。
 *
 * v1.18.0 之前，抖音 / QQ / 百度三端的 profile() 直接返回空 raw（未真实拉取资料），
 * 属于「静默欠交付」；企业微信此前更被错误路由到微信适配器，根本取不到资料。
 * 本测试验证：
 *  - 抖音 profile：真实调用 get_profile，归一化昵称 / 头像 / 性别 / union_id，
 *    未传 access_token 时自动回退到 app token；
 *  - QQ profile：真实调用 graph.qq.com/user/get_user_info（错误字段 ret）；
 *  - 百度 profile：真实调用 smartapp/getuserinfo（错误字段 errno）；
 *  - 企业微信 profile：真实调用 /user/get（以 userid 为键），归一化姓名 / 头像，
 *    原来被错误路由到微信适配器的问题已修复；
 *  - 支付宝 profile：真实调用 alipay.user.info.share，归一化 nick_name / avatar；
 *  - 钉钉 profile：真实调用 topapi/v2/user/get，归一化 name / avatar；
 *  - 飞书 profile：真实调用 contact/v3/users，name / avatar 为嵌套对象，
 *    经适配器归一化为 nick_name / avatar_url（修复静默丢失昵称 / 头像的 bug）；
 *  - 各端在 token / userid 失效时真实抛出 ApiException（杜绝静默失败）；
 *  - QQ / 百度未传 access_token 时优雅返回空 raw（不发起请求）。
 */
class UserProfileTest extends TestCase
{
    private function http(): HttpClientInterface
    {
        return new class implements HttpClientInterface {
            public function get(string $uri, array $options = []): ResponseInterface
            {
                $query = $options['query'] ?? [];
                if (str_contains($uri, '?')) {
                    parse_str((string) parse_url($uri, PHP_URL_QUERY), $embedded);
                    $query = array_merge($embedded, $query);
                }
                return $this->routeGet($uri, $query);
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                $form = $options['form_params'] ?? [];
                if (str_contains($uri, 'user/get_profile')) {
                    $openId = (string) ($form['openid'] ?? '');
                    $token  = (string) ($form['access_token'] ?? '');

                    return $this->douyinProfile($openId, $token);
                }
                if (str_contains($uri, 'gateway.do')) {
                    return $this->routeAlipay((string) ($form['method'] ?? ''));
                }
                return $this->respond([]);
            }

            public function put(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
            }

            public function patch(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
            }

            public function delete(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
            }

            public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
            {
                if (str_contains($uri, 'topapi/v2/user/get')) {
                    return $this->dingtalkProfile((string) ($data['userid'] ?? ''));
                }
                if (str_contains($uri, 'tenant_access_token')) {
                    return $this->respond([
                        'code' => 0, 'msg' => 'ok',
                        'tenant_access_token' => 'LK_TENANT', 'expire' => 7200,
                    ]);
                }
                return $this->respond([]);
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return $this->respond([]);
            }

            /**
             * @param array<string, mixed> $query
             */
            private function routeGet(string $uri, array $query): ResponseInterface
            {
                if (str_contains($uri, 'graph.qq.com/user/get_user_info')) {
                    $openId = (string) ($query['openid'] ?? '');
                    $token  = (string) ($query['access_token'] ?? '');

                    return $this->qqProfile($openId, $token);
                }
                if (str_contains($uri, 'rest/2.0/smartapp/getuserinfo')) {
                    $openId = (string) ($query['openid'] ?? '');
                    $token  = (string) ($query['access_token'] ?? '');

                    return $this->baiduProfile($openId, $token);
                }
                if (str_contains($uri, 'cgi-bin/gettoken')) {
                    return $this->respond([
                        'errcode'     => 0,
                        'errmsg'      => 'ok',
                        'access_token' => 'WW_TOK',
                        'expires_in'  => 7200,
                    ]);
                }
                if (str_contains($uri, 'cgi-bin/user/get')) {
                    $userId = (string) ($query['userid'] ?? '');

                    return $this->weworkProfile($userId);
                }
                if (str_contains($uri, 'v2/token') && str_contains($uri, 'developer.toutiao.com')) {
                    return $this->respond([
                        'err_no' => 0, 'err_tips' => '',
                        'data' => ['access_token' => 'DY_APPTOK', 'expires_in' => 7200],
                    ]);
                }
                if (str_contains($uri, 'contact/v3/users/')) {
                    if (preg_match('#contact/v3/users/([^?/]+)#', $uri, $m) === 1) {
                        return $this->larkProfile($m[1]);
                    }
                    return $this->larkProfile('');
                }
                return $this->respond([]);
            }

            private function douyinProfile(string $openId, string $token): ResponseInterface
            {
                if ($token === 'bad') {
                    return $this->respond(['err_no' => 1, 'err_tips' => 'invalid token']);
                }
                return $this->respond([
                    'err_no' => 0, 'err_tips' => '',
                    'data' => [
                        'openid'   => $openId !== '' ? $openId : 'DY_OPENID',
                        'nick_name' => '抖小',
                        'avatar'   => 'http://dy/avatar.png',
                        'gender'   => '男',
                        'union_id' => 'DY_UNION2',
                    ],
                ]);
            }

            private function qqProfile(string $openId, string $token): ResponseInterface
            {
                if ($token === 'bad') {
                    return $this->respond(['ret' => 100, 'msg' => 'invalid token']);
                }
                return $this->respond([
                    'ret'             => 0,
                    'msg'             => '',
                    'nickname'        => 'QQ小',
                    'figureurl_qq_2'  => 'http://qq/avatar.png',
                    'gender'          => '男',
                    'openid'          => $openId !== '' ? $openId : 'QQ_OPENID',
                ]);
            }

            private function baiduProfile(string $openId, string $token): ResponseInterface
            {
                if ($token === 'bad') {
                    return $this->respond(['errno' => 1, 'msg' => 'invalid token']);
                }
                return $this->respond([
                    'errno' => 0, 'msg' => 'success',
                    'data'  => [
                        'openid'    => $openId !== '' ? $openId : 'BD_OPENID',
                        'nickname'  => '百小',
                        'headimgurl' => 'http://bd/avatar.png',
                        'sex'       => 1,
                    ],
                ]);
            }

            private function weworkProfile(string $userId): ResponseInterface
            {
                if ($userId === 'bad') {
                    return $this->respond(['errcode' => 60111, 'errmsg' => 'userid not found']);
                }
                return $this->respond([
                    'errcode'   => 0,
                    'errmsg'    => 'ok',
                    'userid'    => $userId !== '' ? $userId : 'WW_USER',
                    'name'      => '王五',
                    'avatar'    => 'http://ww/avatar.png',
                    'department' => [1],
                    'position'  => '工程师',
                ]);
            }

            private function routeAlipay(string $method): ResponseInterface
            {
                if ($method === 'alipay.user.info.share') {
                    return $this->respond([
                        'alipay_user_info_share_response' => [
                            'code'      => '10000',
                            'user_id'   => 'ALI_USER',
                            'nick_name' => '支付宝小明',
                            'avatar'    => 'http://ali/avatar.png',
                        ],
                        'sign' => 'x',
                    ]);
                }
                return $this->respond([]);
            }

            private function dingtalkProfile(string $userId): ResponseInterface
            {
                if ($userId === 'bad') {
                    return $this->respond(['errcode' => 60121, 'errmsg' => 'userid not found']);
                }
                return $this->respond([
                    'errcode' => 0, 'errmsg' => 'ok',
                    'result'  => [
                        'userid'  => $userId !== '' ? $userId : 'DT_USER',
                        'name'    => '赵六',
                        'avatar'  => 'http://dt/avatar.png',
                        'unionid' => 'DT_UNION',
                    ],
                ]);
            }

            private function larkProfile(string $userId): ResponseInterface
            {
                if ($userId === 'bad') {
                    return $this->respond(['code' => 19021, 'msg' => 'user not found']);
                }
                return $this->respond([
                    'code' => 0, 'msg' => 'ok',
                    'data' => [
                        'user_id'   => $userId !== '' ? $userId : 'LK_USER',
                        'union_id'  => 'LK_UNION',
                        'open_id'   => 'LK_OPENID',
                        'name'      => ['zh_cn' => '张三', 'en_us' => 'San Zhang'],
                        'avatar'    => [
                            'avatar_origin' => 'http://lk/avatar_origin.png',
                            'avatar_240'    => 'http://lk/avatar_240.png',
                            'avatar_72'     => 'http://lk/avatar_72.png',
                        ],
                    ],
                ]);
            }

            /**
             * @param array<string, mixed> $body
             */
            private function respond(array $body): ResponseInterface
            {
                return new class ($body) implements ResponseInterface {
                    /**
                     * @param array<string, mixed> $body
                     */
                    public function __construct(private array $body)
                    {
                    }

                    public function getProtocolVersion(): string
                    {
                        return '1.1';
                    }

                    public function withProtocolVersion($v): self
                    {
                        return $this;
                    }

                    public function getHeaders(): array
                    {
                        return [];
                    }

                    public function hasHeader($n): bool
                    {
                        return false;
                    }

                    public function getHeader($n): array
                    {
                        return [];
                    }

                    public function getHeaderLine($n): string
                    {
                        return '';
                    }

                    public function withHeader($n, $v): self
                    {
                        return $this;
                    }

                    public function withAddedHeader($n, $v): self
                    {
                        return $this;
                    }

                    public function withoutHeader($n): self
                    {
                        return $this;
                    }

                    public function getBody(): StreamInterface
                    {
                        return new class ($this->body) implements StreamInterface {
                            /**
                             * @param array<string, mixed> $body
                             */
                            public function __construct(private array $body)
                            {
                            }

                            public function __toString(): string
                            {
                                return (string) json_encode($this->body);
                            }

                            public function close(): void
                            {
                            }

                            public function detach()
                            {
                                return null;
                            }

                            public function getSize(): ?int
                            {
                                return null;
                            }

                            public function tell(): int
                            {
                                return 0;
                            }

                            public function eof(): bool
                            {
                                return true;
                            }

                            public function isSeekable(): bool
                            {
                                return false;
                            }

                            public function seek($o, $w = SEEK_SET): void
                            {
                            }

                            public function rewind(): void
                            {
                            }

                            public function isWritable(): bool
                            {
                                return false;
                            }

                            public function write($s): int
                            {
                                return 0;
                            }

                            public function isReadable(): bool
                            {
                                return true;
                            }

                            public function read($l): string
                            {
                                return '';
                            }

                            public function getContents(): string
                            {
                                return (string) json_encode($this->body);
                            }

                            public function getMetadata($k = null)
                            {
                                return null;
                            }
                        };
                    }

                    public function withBody(StreamInterface $b): self
                    {
                        return $this;
                    }

                    public function getStatusCode(): int
                    {
                        return 200;
                    }

                    public function withStatus($c, $r = ''): self
                    {
                        return $this;
                    }

                    public function getReasonPhrase(): string
                    {
                        return 'OK';
                    }
                };
            }
        };
    }

    private function kernel(): Kernel
    {
        return new Kernel(
            [
                'douyin' => [
                    'app_id' => 'tt123',
                    'secret' => 's3cr3t',
                    'cache'  => new ArrayCache(),
                ],
                'qq' => [
                    'app_id' => 'qq_app',
                    'secret' => 's3cr3t',
                ],
                'baidu' => [
                    'app_id' => 'bd_app',
                    'secret' => 's3cr3t',
                ],
                'wechat_work' => [
                    'corp_id'  => 'ww_corp',
                    'secret'   => 's3cr3t',
                    'agent_id' => '1000002',
                    'cache'    => new ArrayCache(),
                ],
                'alipay' => [
                    'app_id'      => 'ali_app',
                    'secret'      => 's3cr3t',
                    'private_key' => $this->rsaKey(),
                ],
                'dingtalk' => [
                    'app_key'    => 'dt_key',
                    'app_secret' => 's3cr3t',
                    'cache'      => new ArrayCache(),
                ],
                'lark' => [
                    'app_id' => 'lk_app',
                    'secret' => 's3cr3t',
                    'cache'  => new ArrayCache(),
                ],
            ],
            $this->http(),
        );
    }

    /**
     * 生成测试用 RSA 私钥（支付宝网关签名需要有效私钥，空密钥会抛 ConfigException）
     */
    private function rsaKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'bits' => 2048]);
        if ($key === false) {
            self::fail('openssl_pkey_new 生成测试私钥失败');
        }
        openssl_pkey_export($key, $privateKey);

        return $privateKey;
    }

    // ===== 抖音 =====

    public function testDouyinProfileFetchesRealData(): void
    {
        $user = $this->kernel()->union()->profile(Channel::DouyinMini, 'DY_OPENID', ['access_token' => 'DY_TOK']);

        self::assertSame('DY_OPENID', $user->openId);
        self::assertSame('DY_UNION2', $user->unionId, '抖音资料接口的 union_id 应归一化');
        self::assertSame('抖小', $user->nickname, '抖音 nick_name 应归一化为 nickname');
        self::assertSame('http://dy/avatar.png', $user->avatar);
        self::assertSame('男', $user->gender);
    }

    public function testDouyinProfileFallsBackToAppToken(): void
    {
        // 未传 access_token，应自动回退到服务端 app token 后拉取资料
        $user = $this->kernel()->union()->profile(Channel::DouyinMini, 'DY_OPENID', []);

        self::assertSame('DY_OPENID', $user->openId);
        self::assertSame('抖小', $user->nickname);
        self::assertSame('http://dy/avatar.png', $user->avatar);
    }

    public function testDouyinProfileErrorThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->profile(Channel::DouyinMini, 'DY_OPENID', ['access_token' => 'bad']);
    }

    // ===== QQ =====

    public function testQqProfileFetchesRealData(): void
    {
        $user = $this->kernel()->union()->profile(Channel::Qq, 'QQ_OPENID', ['access_token' => 'QQ_TOK']);

        self::assertSame('QQ_OPENID', $user->openId);
        self::assertSame('QQ小', $user->nickname);
        self::assertSame('http://qq/avatar.png', $user->avatar, 'QQ figureurl_qq_2 应归一化为 avatar');
        self::assertSame('男', $user->gender);
    }

    public function testQqProfileErrorThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->profile(Channel::Qq, 'QQ_OPENID', ['access_token' => 'bad']);
    }

    public function testQqProfileWithoutTokenReturnsEmptyRaw(): void
    {
        $user = $this->kernel()->union()->profile(Channel::Qq, 'QQ_OPENID', []);

        self::assertSame('QQ_OPENID', $user->openId);
        self::assertNull($user->nickname);
        self::assertNull($user->avatar);
        self::assertSame([], $user->raw, '未传 access_token 不应发起请求');
    }

    // ===== 百度 =====

    public function testBaiduProfileFetchesRealData(): void
    {
        $user = $this->kernel()->union()->profile(Channel::BaiduMini, 'BD_OPENID', ['access_token' => 'BD_TOK']);

        self::assertSame('BD_OPENID', $user->openId);
        self::assertSame('百小', $user->nickname);
        self::assertSame('http://bd/avatar.png', $user->avatar);
        self::assertSame('male', $user->gender, '百度 sex=1 应映射为 male');
    }

    public function testBaiduProfileErrorThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->profile(Channel::BaiduMini, 'BD_OPENID', ['access_token' => 'bad']);
    }

    public function testBaiduProfileWithoutTokenReturnsEmptyRaw(): void
    {
        $user = $this->kernel()->union()->profile(Channel::BaiduMini, 'BD_OPENID', []);

        self::assertSame('BD_OPENID', $user->openId);
        self::assertNull($user->nickname);
        self::assertNull($user->avatar);
        self::assertSame([], $user->raw, '未传 access_token 不应发起请求');
    }

    // ===== 企业微信 =====

    public function testWeWorkProfileFetchesRealData(): void
    {
        // openId 在登录阶段已被规范为 userid，直接用于 /user/get
        $user = $this->kernel()->union()->profile(Channel::WechatWork, 'WW_USER', []);

        self::assertSame('WW_USER', $user->openId);
        self::assertSame('王五', $user->nickname, '企业微信 name 应归一化为 nickname');
        self::assertSame('http://ww/avatar.png', $user->avatar);
        self::assertSame(Channel::WechatWork, $user->channel);
    }

    public function testWeWorkProfileAcceptsExplicitUserid(): void
    {
        $user = $this->kernel()->union()->profile(Channel::WechatWork, 'WW_USER', ['userid' => 'WW_OTHER']);

        self::assertSame('WW_OTHER', $user->openId, 'payload[userid] 应覆盖 openId 作为查询键');
        self::assertSame('王五', $user->nickname);
    }

    public function testWeWorkProfileErrorThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->profile(Channel::WechatWork, 'bad', []);
    }

    // ===== 支付宝 =====

    public function testAlipayProfileFetchesRealData(): void
    {
        $user = $this->kernel()->union()->profile(Channel::AlipayMini, 'ALI_OPENID', ['access_token' => 'ALI_TOK']);

        self::assertSame('ALI_OPENID', $user->openId);
        self::assertSame('支付宝小明', $user->nickname, '支付宝 nick_name 应归一化为 nickname');
        self::assertSame('http://ali/avatar.png', $user->avatar);
        self::assertSame(Channel::AlipayMini, $user->channel);
    }

    public function testAlipayProfileWithoutTokenReturnsEmptyRaw(): void
    {
        $user = $this->kernel()->union()->profile(Channel::AlipayMini, 'ALI_OPENID', []);

        self::assertSame('ALI_OPENID', $user->openId);
        self::assertNull($user->nickname);
        self::assertNull($user->avatar);
        self::assertSame([], $user->raw, '未传 access_token 不应发起请求');
    }

    // ===== 钉钉 =====

    public function testDingtalkProfileFetchesRealData(): void
    {
        $user = $this->kernel()->union()->profile(Channel::Dingtalk, 'DT_USER', []);

        self::assertSame('DT_USER', $user->openId);
        self::assertSame('赵六', $user->nickname, '钉钉 name 应归一化为 nickname');
        self::assertSame('http://dt/avatar.png', $user->avatar);
        self::assertSame(Channel::Dingtalk, $user->channel);
    }

    public function testDingtalkProfileErrorThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->profile(Channel::Dingtalk, 'bad', []);
    }

    // ===== 飞书 =====

    public function testLarkProfileNormalizesNestedNameAndAvatar(): void
    {
        // 飞书 contact/v3/users 返回 name/avatar 为嵌套对象，适配器需归一化
        $user = $this->kernel()->union()->profile(Channel::Lark, 'LK_USER', []);

        self::assertSame('LK_USER', $user->openId);
        self::assertSame('LK_UNION', $user->unionId);
        self::assertSame('张三', $user->nickname, '飞书嵌套 name.zh_cn 应归一化为 nickname');
        self::assertSame(
            'http://lk/avatar_origin.png',
            $user->avatar,
            '飞书嵌套 avatar.avatar_origin 应归一化为 avatar'
        );
        self::assertSame(Channel::Lark, $user->channel);
    }

    public function testLarkProfileErrorThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->profile(Channel::Lark, 'bad', []);
    }
}
