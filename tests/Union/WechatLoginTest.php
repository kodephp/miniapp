<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 微信开放平台绑定场景：公众号 / 小程序一键登录 + 获取用户信息
 *
 * 验证同一开放平台下，公众号与小程序登录均返回相同 unionId（跨端关联），
 * 且登录后可通过 openId 拉取用户资料（公众号自动解析 mp access_token）。
 */
class WechatLoginTest extends TestCase
{
    /**
     * 按 URL 关键字路由的 mock HTTP 客户端
     *
     * @param array<string, array<string, mixed>> $routes
     */
    private function http(array $routes): HttpClientInterface
    {
        return new class ($routes) implements HttpClientInterface {
            /**
             * @param array<string, array<string, mixed>> $routes
             */
            public function __construct(private array $routes)
            {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                return $this->route($uri);
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
                return $this->respond([]);
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return $this->respond([]);
            }

            private function route(string $uri): ResponseInterface
            {
                foreach ($this->routes as $needle => $body) {
                    if (str_contains($uri, $needle)) {
                        return $this->respond($body);
                    }
                }
                return $this->respond([]);
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

    /**
     * 构造带 mock HTTP 的 Kernel（复用同一开放平台 → 相同 unionId）
     */
    private function kernel(): Kernel
    {
        $routes = [
            'sns/oauth2/access_token' => [
                'access_token' => 'MP_OAUTH_TOK',
                'openid'       => 'OPENID_MP',
                'unionid'      => 'UNION_001',
                'expires_in'   => 7200,
            ],
            'sns/userinfo' => [
                'openid'     => 'OPENID_MP',
                'nickname'   => '公众号小明',
                'headimgurl' => 'http://img/mp.png',
                'unionid'    => 'UNION_001',
            ],
            'jscode2session' => [
                'openid'      => 'OPENID_MINI',
                'unionid'     => 'UNION_001',
                'session_key' => 'SK_xxx',
            ],
            'cgi-bin/token' => [
                'access_token' => 'MP_TOK',
                'expires_in'   => 7200,
            ],
            'cgi-bin/user/info' => [
                'openid'     => 'OPENID_MP',
                'nickname'   => '资料小红',
                'headimgurl' => 'http://img/mp2.png',
                'unionid'    => 'UNION_001',
                'sex'        => 1,
            ],
        ];

        return new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx123',
                    'secret' => 's3cr3t',
                    'cache'  => new ArrayCache(),
                ],
            ],
            $this->http($routes),
        );
    }

    public function testMpLoginReturnsUnionIdAndNickname(): void
    {
        $user = $this->kernel()->union()->wechat()->mp('OAUTH_CODE');

        self::assertInstanceOf(UnionUser::class, $user);
        self::assertSame('OPENID_MP', $user->openId);
        self::assertSame('UNION_001', $user->unionId, '开放平台绑定后公众号登录应携带 unionId');
        self::assertSame('公众号小明', $user->nickname);
        self::assertSame('http://img/mp.png', $user->avatar);
    }

    public function testMiniLoginReturnsUnionId(): void
    {
        $user = $this->kernel()->union()->wechat()->mini('JS_CODE');

        self::assertInstanceOf(UnionUser::class, $user);
        self::assertSame('OPENID_MINI', $user->openId);
        self::assertSame('UNION_001', $user->unionId, '开放平台绑定后小程序登录应携带相同 unionId');
    }

    public function testSameUnionIdAcrossBoundApps(): void
    {
        $kernel = $this->kernel();
        $mp    = $kernel->union()->wechat()->mp('OAUTH_CODE');
        $mini  = $kernel->union()->wechat()->mini('JS_CODE');

        self::assertSame($mp->unionId, $mini->unionId, '公众号与小程序绑定同一开放平台，unionId 应一致');
        self::assertSame('UNION_001', $mp->unionId);
    }

    public function testMpProfileFetchesUserInfoWithAutoToken(): void
    {
        // 不手动传 access_token，应由适配器自动解析 mp access_token 拉取资料
        $user = $this->kernel()->union()->wechat()->user('OPENID_MP', [], 'mp');

        self::assertInstanceOf(UnionUser::class, $user);
        self::assertSame('OPENID_MP', $user->openId);
        self::assertSame('UNION_001', $user->unionId);
        self::assertSame('资料小红', $user->nickname);
        self::assertSame('http://img/mp2.png', $user->avatar);
    }
}
