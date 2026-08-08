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
 * 微信开放平台绑定后的一键登录 + 用户信息 端到端集成测试。
 *
 * 通过按 URL / 参数路由的 Mock HttpClient 模拟微信各真实端点，
 * 验证：
 *  - 小程序 / 公众号 / App / PC 各渠道登录均返回正确的 openid 与 unionid
 *  - 开放平台绑定后各渠道 unionid 一致（跨端账号关联）
 *  - 公众号 / App / PC 的用户资料真实拉取
 *  - 无效 code / 授权失败时真实抛错（真实对接）
 *  - 第三方平台（component）授权码换 token 的成功与失败路径
 */
class OpenPlatformLoginTest extends TestCase
{
    public const UNION = 'U_OPEN_1';

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
                return $this->route($uri, $query);
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
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
                $query = [];
                if (str_contains($uri, '?')) {
                    parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
                }
                if (str_contains($uri, 'api_query_auth')) {
                    $authCode = (string) ($data['authorization_code'] ?? $query['authorization_code'] ?? '');
                    if ($authCode === 'bad_auth') {
                        return $this->respond(['errcode' => 40013, 'errmsg' => 'invalid appid']);
                    }
                    return $this->respond([
                        'authorization_info' => [
                            'authorizer_appid'         => 'auth_appid_1',
                            'authorizer_access_token'  => 'AUTH_TOK',
                            'authorizer_refresh_token' => 'AUTH_REFRESH',
                            'expires_in'               => 7200,
                        ],
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
            private function route(string $uri, array $query): ResponseInterface
            {
                $appId  = (string) ($query['appid'] ?? '');
                $openId = (string) ($query['openid'] ?? '');
                $code   = (string) ($query['js_code'] ?? $query['code'] ?? '');

                if (str_contains($uri, 'jscode2session')) {
                    if ($code === 'bad_code') {
                        return $this->respond(['errcode' => 40029, 'errmsg' => 'invalid code']);
                    }
                    return $this->respond([
                        'openid'      => 'oMINI',
                        'session_key' => 'sk',
                        'unionid'     => OpenPlatformLoginTest::UNION,
                    ]);
                }

                if (str_contains($uri, 'oauth2/access_token')) {
                    if ($code === 'bad_code') {
                        return $this->respond(['errcode' => 40029, 'errmsg' => 'invalid code']);
                    }
                    if ($appId === 'wx_app') {
                        return $this->respond([
                            'access_token' => 'APP_TOK', 'openid' => 'oAPP',
                            'unionid' => OpenPlatformLoginTest::UNION, 'scope' => 'snsapi_userinfo',
                        ]);
                    }
                    return $this->respond([
                        'access_token' => 'MP_TOK', 'openid' => 'oMP',
                        'unionid' => OpenPlatformLoginTest::UNION, 'scope' => 'snsapi_userinfo',
                    ]);
                }

                if (str_contains($uri, 'sns/userinfo')) {
                    if ($openId === 'oAPP') {
                        return $this->respond([
                            'openid' => 'oAPP', 'nickname' => 'App昵称',
                            'headimgurl' => 'http://app', 'sex' => 1, 'unionid' => OpenPlatformLoginTest::UNION,
                        ]);
                    }
                    return $this->respond([
                        'openid' => 'oMP', 'nickname' => 'Mp昵称',
                        'headimgurl' => 'http://mp', 'sex' => 1, 'unionid' => OpenPlatformLoginTest::UNION,
                    ]);
                }

                if (str_contains($uri, 'cgi-bin/token')) {
                    return $this->respond(['access_token' => 'CGI_TOK', 'expires_in' => 7200]);
                }

                if (str_contains($uri, 'cgi-bin/user/info')) {
                    return $this->respond([
                        'openid' => $openId ?: 'oMP', 'nickname' => 'Mp资料',
                        'headimgurl' => 'http://mp', 'sex' => 1, 'province' => '广东',
                        'city' => '深圳', 'unionid' => OpenPlatformLoginTest::UNION,
                    ]);
                }

                return $this->respond([]);
            }

            /**
             * @param array<string, mixed> $body
             */
            private function respond(array $body, int $status = 200): ResponseInterface
            {
                return new class ($body, $status) implements ResponseInterface {
                    /**
                     * @param array<string, mixed> $body
                     */
                    public function __construct(private array $body, private int $status)
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
                        return $this->status;
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

    private function kernel(HttpClientInterface $http): Kernel
    {
        return new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx_mp',
                    'secret' => 's3cr3t',
                    'cache'  => new ArrayCache(),
                ],
                'wechat_open' => [
                    'mobile_app_id'  => 'wx_app',
                    'mobile_secret' => 's3cr3t',
                    'site_app_id'    => 'wx_app',
                    'site_secret'    => 's3cr3t',
                    'cache'          => new ArrayCache(),
                ],
            ],
            $http,
        );
    }

    public function testMiniLoginReturnsOpenidAndUnionId(): void
    {
        $kernel = $this->kernel($this->http());
        $user   = $kernel->union()->wechat()->mini('JS_CODE');

        self::assertSame('oMINI', $user->openId);
        self::assertSame(self::UNION, $user->unionId);
        self::assertSame(Channel::WechatMini, $user->channel);
    }

    public function testMpLoginReturnsUserBasicInfo(): void
    {
        $kernel = $this->kernel($this->http());
        $user   = $kernel->union()->wechat()->mp('OAUTH_CODE');

        self::assertSame('oMP', $user->openId);
        self::assertSame(self::UNION, $user->unionId);
        self::assertSame('Mp昵称', $user->nickname);
        self::assertSame('http://mp', $user->avatar);
        self::assertSame('male', $user->gender);
        self::assertArrayHasKey('access_token', $user->extra);
    }

    public function testAppLoginReturnsUnionId(): void
    {
        $kernel = $this->kernel($this->http());
        $user   = $kernel->union()->wechat()->app('APP_CODE');

        self::assertSame('oAPP', $user->openId);
        self::assertSame(self::UNION, $user->unionId);
        self::assertSame('App昵称', $user->nickname);
        self::assertSame(Channel::WechatApp, $user->channel);
    }

    public function testPcLoginReturnsUnionId(): void
    {
        $kernel = $this->kernel($this->http());
        $user   = $kernel->union()->wechat()->pc('PC_CODE');

        self::assertSame('oAPP', $user->openId);
        self::assertSame(self::UNION, $user->unionId);
        self::assertSame('App昵称', $user->nickname);
        self::assertSame(Channel::WechatPc, $user->channel);
    }

    public function testCrossChannelUnionIdIsConsistent(): void
    {
        $kernel = $this->kernel($this->http());

        $mini = $kernel->union()->wechat()->mini('JS_CODE');
        $mp   = $kernel->union()->wechat()->mp('OAUTH_CODE');
        $app  = $kernel->union()->wechat()->app('APP_CODE');
        $pc   = $kernel->union()->wechat()->pc('PC_CODE');

        // 微信开放平台绑定后，各渠道共享同一 unionId —— 跨端账号关联的关键
        self::assertSame(self::UNION, $mini->unionId);
        self::assertSame(self::UNION, $mp->unionId);
        self::assertSame(self::UNION, $app->unionId);
        self::assertSame(self::UNION, $pc->unionId);
    }

    public function testMpProfileAutoFetchWithoutToken(): void
    {
        $kernel = $this->kernel($this->http());
        $user   = $kernel->union()->profile(Channel::WechatMp, 'oMP', []);

        self::assertSame('oMP', $user->openId);
        self::assertSame(self::UNION, $user->unionId);
        self::assertSame('Mp资料', $user->nickname);
        self::assertSame('广东', $user->province);
        self::assertSame('深圳', $user->city);
    }

    public function testAppProfileWithAccessToken(): void
    {
        $kernel = $this->kernel($this->http());
        $user   = $kernel->union()->profile(
            Channel::WechatApp,
            'oAPP',
            ['channel' => 'wechat_app', 'access_token' => 'APP_TOK']
        );

        self::assertSame('oAPP', $user->openId);
        self::assertSame(self::UNION, $user->unionId);
        self::assertSame('App昵称', $user->nickname);
    }

    public function testAppLoginInvalidCodeThrows(): void
    {
        $kernel = $this->kernel($this->http());

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/invalid code|40029/');
        $kernel->union()->wechat()->app('bad_code');
    }

    public function testMpLoginInvalidCodeThrows(): void
    {
        $kernel = $this->kernel($this->http());

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/invalid code|40029/');
        $kernel->union()->wechat()->mp('bad_code');
    }

    public function testComponentLoginSuccess(): void
    {
        $kernel = $this->kernel($this->http());
        $user   = $kernel->union()->wechatOpen()->open([
            'authorization_code'     => 'AUTH_CODE',
            'component_access_token' => 'COMP_TOK',
        ]);

        self::assertSame('auth_appid_1', $user->openId);
        self::assertArrayHasKey('authorizer_access_token', $user->extra);
    }

    public function testComponentLoginInvalidCodeThrows(): void
    {
        $kernel = $this->kernel($this->http());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/授权码换取失败|40013/');
        $kernel->union()->wechatOpen()->open([
            'authorization_code'     => 'bad_auth',
            'component_access_token' => 'COMP_TOK',
        ]);
    }
}
