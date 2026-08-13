<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatOpen;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatOpen\Modules\Authorizer;
use Kode\MiniApp\Providers\WechatOpen\Modules\Component;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Tests\Fakes\FakeResponse;
use Kode\MiniApp\Union\Channel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 微信开放平台 Component / Authorizer / UnionId 模块测试
 */
class WechatOpenModuleTest extends TestCase
{
    public function testLoginPageUrl(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app  = $kernel->wechatOpen()->app();
        $page = $app->component()->loginPage(
            preAuthCode: 'preauth_001',
            redirectUri: 'https://example.com/callback',
            authType: 1,
        );

        self::assertStringContainsString('componentloginpage', $page);
        self::assertStringContainsString('component_appid=wxcomp123', $page);
        self::assertStringContainsString('pre_auth_code=preauth_001', $page);
        self::assertStringContainsString('redirect_uri=', $page);
        self::assertStringContainsString('wechat_redirect', $page);
    }

    public function testLoginPageUrlWithBizApp(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();
        $page = $app->component()->loginPage(
            preAuthCode: 'preauth_002',
            redirectUri: 'https://example.com/callback',
            authType: 3,
            bizAppId: 'wxbiz123',
        );

        self::assertStringContainsString('biz_appid=wxbiz123', $page);
        self::assertStringContainsString('auth_type=3', $page);
    }

    public function testVerifySignature(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();
        $component = $app->component();
        $token     = 'verify-token';
        $time      = '1700000000';
        $nonce     = 'abc123';
        $enc       = 'encrypted-msg';

        $tmp   = [$token, $time, $nonce, $enc];
        sort($tmp, SORT_STRING);
        $sig   = sha1(implode('', $tmp));

        self::assertTrue($component->verifySignature($time, $nonce, $enc, $sig));
        self::assertFalse($component->verifySignature($time, $nonce, $enc, 'wrong-sig'));
    }

    public function testSignAndVerifyJsApi(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();
        $component = $app->component();
        $url       = 'https://example.com/page';
        $params    = ['jsapi_ticket' => 'ticket', 'noncestr' => 'xyz', 'timestamp' => '1700'];

        $sig = $component->signForJsSdk($params, $url);
        self::assertTrue($component->verifyJsApiSignature($params, $url, $sig));
        self::assertFalse($component->verifyJsApiSignature($params, $url, 'wrong'));
    }

    public function testUnionIdExtraction(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();
        $unionId = $app->unionId();

        self::assertSame('UID_001', $unionId->fromPayload(['unionid' => 'UID_001']));
        self::assertNull($unionId->fromPayload(['openid' => 'OID_001']));
        self::assertTrue($unionId->belongsToCurrent(['unionid' => 'UID_001']));
        self::assertFalse($unionId->belongsToCurrent(['openid' => 'OID_001']));
        self::assertSame('kode_wechat_unionid_app_UID_001', $unionId->cacheKey('UID_001', 'app'));
    }

    public function testComponentAccessTokenCall(): void
    {
        $stub = new class implements HttpClientInterface {
            /** @var array<string, mixed>|null */
            public ?array $lastData = null;
            public ?string $lastUri = null;

            public function get(string $uri, array $options = []): ResponseInterface
            {
                return $this->respondWith([]);
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                $this->lastUri = $uri;

                return $this->respondWith([]);
            }

            public function put(string $uri, array $options = []): ResponseInterface
            {
                return $this->respondWith([]);
            }

            public function patch(string $uri, array $options = []): ResponseInterface
            {
                return $this->respondWith([]);
            }

            public function delete(string $uri, array $options = []): ResponseInterface
            {
                return $this->respondWith([]);
            }

            public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
            {
                $this->lastUri  = $uri;
                $this->lastData = $data;

                return $this->respondWith([
                    'component_access_token' => 'COMP_TOK_001',
                    'expires_in'             => 7200,
                ]);
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return $this->respondWith([]);
            }

            /**
             * @param array<string, mixed> $body
             */
            private function respondWith(array $body): ResponseInterface
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
                    public function withProtocolVersion($version): self
                    {
                        return $this;
                    }
                    public function getHeaders(): array
                    {
                        return [];
                    }
                    public function hasHeader($name): bool
                    {
                        return false;
                    }
                    public function getHeader($name): array
                    {
                        return [];
                    }
                    public function getHeaderLine($name): string
                    {
                        return '';
                    }
                    public function withHeader($name, $value): self
                    {
                        return $this;
                    }
                    public function withAddedHeader($name, $value): self
                    {
                        return $this;
                    }
                    public function withoutHeader($name): self
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
                            public function seek($offset, $whence = SEEK_SET): void
                            {
                            }
                            public function rewind(): void
                            {
                            }
                            public function isWritable(): bool
                            {
                                return false;
                            }
                            public function write($string): int
                            {
                                return 0;
                            }
                            public function isReadable(): bool
                            {
                                return true;
                            }
                            public function read($length): string
                            {
                                return '';
                            }
                            public function getContents(): string
                            {
                                return (string) json_encode($this->body);
                            }
                            public function getMetadata($key = null)
                            {
                                return null;
                            }
                        };
                    }
                    public function withBody(StreamInterface $body): self
                    {
                        return $this;
                    }
                    public function getStatusCode(): int
                    {
                        return 200;
                    }
                    public function withStatus($code, $reasonPhrase = ''): self
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

        $kernel = new Kernel(
            [
                'wechat_open' => [
                    'component_appid'  => 'wxcomp123',
                    'component_secret' => 'comp-secret',
                    'token'            => 'verify-token',
                    'encoding_aes_key' => str_repeat('a', 43),
                ],
            ],
            $stub,
        );

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();
        $result = $app->component()->accessToken('TICKET_001');

        self::assertSame('COMP_TOK_001', $result['component_access_token']);
        self::assertSame(7200, $result['expires_in']);
        self::assertIsArray($stub->lastData);
        self::assertSame('TICKET_001', $stub->lastData['component_verify_ticket'] ?? null);
        self::assertSame('wxcomp123', $stub->lastData['component_appid'] ?? null);
    }

    public function testAuthorizerCall(): void
    {
        $stub = new class implements HttpClientInterface {
            public ?string $lastUri = null;
            /** @var array<string, mixed>|null */
            public ?array $lastData = null;

            public function get(string $uri, array $options = []): ResponseInterface
            {
                $this->lastUri = $uri;

                return $this->respond();
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                $this->lastUri = $uri;
                if (isset($options['json'])) {
                    $this->lastData = is_array($options['json']) ? $options['json'] : [];
                } elseif (isset($options['form_params'])) {
                    $this->lastData = is_array($options['form_params']) ? $options['form_params'] : [];
                }

                return $this->respond();
            }

            public function put(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond();
            }
            public function patch(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond();
            }
            public function delete(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond();
            }
            public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
            {
                $this->lastUri = $uri;
                $this->lastData = $data;

                return $this->respond();
            }
            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return $this->respond();
            }

            private function respond(): ResponseInterface
            {
                $body = ['errcode' => 0, 'errmsg' => 'ok'];
                $stream = new class ($body) implements StreamInterface {
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
                    public function seek($offset, $whence = SEEK_SET): void
                    {
                    }
                    public function rewind(): void
                    {
                    }
                    public function isWritable(): bool
                    {
                        return false;
                    }
                    public function write($string): int
                    {
                        return 0;
                    }
                    public function isReadable(): bool
                    {
                        return true;
                    }
                    public function read($length): string
                    {
                        return '';
                    }
                    public function getContents(): string
                    {
                        return (string) json_encode($this->body);
                    }
                    public function getMetadata($key = null)
                    {
                        return null;
                    }
                };

                return new class ($stream) implements ResponseInterface {
                    public function __construct(private StreamInterface $stream)
                    {
                    }
                    public function getProtocolVersion(): string
                    {
                        return '1.1';
                    }
                    public function withProtocolVersion($version): self
                    {
                        return $this;
                    }
                    public function getHeaders(): array
                    {
                        return [];
                    }
                    public function hasHeader($name): bool
                    {
                        return false;
                    }
                    public function getHeader($name): array
                    {
                        return [];
                    }
                    public function getHeaderLine($name): string
                    {
                        return '';
                    }
                    public function withHeader($name, $value): self
                    {
                        return $this;
                    }
                    public function withAddedHeader($name, $value): self
                    {
                        return $this;
                    }
                    public function withoutHeader($name): self
                    {
                        return $this;
                    }
                    public function getBody(): StreamInterface
                    {
                        return $this->stream;
                    }
                    public function withBody(StreamInterface $body): self
                    {
                        return $this;
                    }
                    public function getStatusCode(): int
                    {
                        return 200;
                    }
                    public function withStatus($code, $reasonPhrase = ''): self
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

        $kernel = new Kernel(
            [
                'wechat_open' => [
                    'component_appid'  => 'wxcomp123',
                    'component_secret' => 'comp-secret',
                    'token'            => 'verify-token',
                    'encoding_aes_key' => str_repeat('a', 43),
                ],
            ],
            $stub,
        );

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();
        $authorizer = $app->authorizer();
        $authorizer->sendCustomerServiceMessage(
            'AUTH_TOK',
            'OPENID_001',
            ['msgtype' => 'text', 'text' => ['content' => 'hi']],
        );

        self::assertIsString($stub->lastUri);
        self::assertStringContainsString('message/custom/send', $stub->lastUri);
        self::assertIsArray($stub->lastData);
        self::assertSame('OPENID_001', $stub->lastData['touser'] ?? null);
        self::assertSame('text', $stub->lastData['msgtype'] ?? null);
    }

    public function testAllAuthorizersAutoPaging(): void
    {
        $stub = new class implements HttpClientInterface {
            public int $callCount = 0;
            /** @var array<int, array<string, mixed>> */
            public array $pages = [];

            public function get(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
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
                $body = $this->pages[$this->callCount] ?? [];
                $this->callCount++;

                return $this->respond($body);
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
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
                    public function withProtocolVersion($version): self
                    {
                        return $this;
                    }
                    public function getHeaders(): array
                    {
                        return [];
                    }
                    public function hasHeader($name): bool
                    {
                        return false;
                    }
                    public function getHeader($name): array
                    {
                        return [];
                    }
                    public function getHeaderLine($name): string
                    {
                        return '';
                    }
                    public function withHeader($name, $value): self
                    {
                        return $this;
                    }
                    public function withAddedHeader($name, $value): self
                    {
                        return $this;
                    }
                    public function withoutHeader($name): self
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
                            public function seek($offset, $whence = SEEK_SET): void
                            {
                            }
                            public function rewind(): void
                            {
                            }
                            public function isWritable(): bool
                            {
                                return false;
                            }
                            public function write($string): int
                            {
                                return 0;
                            }
                            public function isReadable(): bool
                            {
                                return true;
                            }
                            public function read($length): string
                            {
                                return '';
                            }
                            public function getContents(): string
                            {
                                return (string) json_encode($this->body);
                            }
                            public function getMetadata($key = null)
                            {
                                return null;
                            }
                        };
                    }
                    public function withBody(StreamInterface $body): self
                    {
                        return $this;
                    }
                    public function getStatusCode(): int
                    {
                        return 200;
                    }
                    public function withStatus($code, $reasonPhrase = ''): self
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

        $stub->pages = [
            ['list' => [['appid' => 'wx1'], ['appid' => 'wx2']]],
            ['list' => [['appid' => 'wx3']]],
        ];

        $kernel = new Kernel(
            [
                'wechat_open' => [
                    'component_appid'  => 'wxcomp123',
                    'component_secret' => 'comp-secret',
                    'token'            => 'verify-token',
                    'encoding_aes_key' => str_repeat('a', 43),
                ],
            ],
            $stub,
        );

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();
        $all = $app->component()->allAuthorizers('COMP_TOKEN', pageSize: 2);

        self::assertCount(3, $all);
        self::assertSame('wx1', $all[0]['appid']);
        self::assertSame('wx2', $all[1]['appid']);
        self::assertSame('wx3', $all[2]['appid']);
        self::assertSame(2, $stub->callCount);
    }

    public function testMiniProgramSessionPassesComponentToken(): void
    {
        $captured = new \stdClass();
        $stub = new class ($captured) implements HttpClientInterface {
            public function __construct(private \stdClass $captured)
            {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                $this->captured->uri   = $uri;
                $this->captured->query = $options['query'] ?? [];

                return new FakeResponse(['openid' => 'OID', 'session_key' => 'SK', 'unionid' => 'UID']);
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function put(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function patch(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function delete(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return new FakeResponse([]);
            }
        };

        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 't',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ], $stub);

        /** @var WechatOpenApp $app */
        $app     = $kernel->wechatOpen()->app();
        $session = $app->authorizer()->miniProgramSession('wxd1234567890', 'JS_CODE', 'COMP_TOK_001');

        self::assertSame('OID', $session['openid']);
        self::assertSame('UID', $session['unionid']);
        self::assertSame('COMP_TOK_001', $captured->query['component_access_token'] ?? null);
        self::assertSame('wxcomp123', $captured->query['component_appid'] ?? null);
        self::assertSame('wxd1234567890', $captured->query['appid'] ?? null);
    }

    public function testBelongsToCurrentScoped(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 't',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app  = $kernel->wechatOpen()->app();
        $uid  = $app->unionId();

        // 未提供授权方集合：退化为「unionid 存在」判断
        self::assertTrue($uid->belongsToCurrent(['unionid' => 'UID_001']));
        self::assertFalse($uid->belongsToCurrent(['openid' => 'OID_001']));

        // 提供已知授权方集合：按 authorizer_appid 做真实归属判定
        $known = ['wxd1234567890', 'wxo0987654321'];
        self::assertTrue($uid->belongsToCurrent(
            ['unionid' => 'UID_001', 'authorizer_appid' => 'wxd1234567890'],
            $known,
        ));
        self::assertFalse($uid->belongsToCurrent(
            ['unionid' => 'UID_001', 'authorizer_appid' => 'wx_other'],
            $known,
        ));
    }

    public function testOpenPlatformLoginReturnsAccountAuthorization(): void
    {
        $stub = new class implements HttpClientInterface {
            public function get(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function put(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function patch(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function delete(string $uri, array $options = []): ResponseInterface
            {
                return new FakeResponse([]);
            }

            public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
            {
                return new FakeResponse([
                    'authorization_info' => [
                        'authorizer_appid'         => 'wxd1234567890',
                        'authorizer_access_token'  => 'AUTH_TOK',
                        'authorizer_refresh_token' => 'REF_TOK',
                    ],
                ]);
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return new FakeResponse([]);
            }
        };

        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 't',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ], $stub);

        $user = $kernel->union()->openPlatform()->login([
            'authorization_code'     => 'AUTH_CODE',
            'component_access_token' => 'COMP_TOK',
        ]);

        self::assertSame(Channel::WechatOpen, $user->channel);
        // 这是「账号授权」结果，不是终端用户：openId / unionId 必须为空
        self::assertSame('', $user->openId);
        self::assertSame('', $user->unionId);
        self::assertSame('wxd1234567890', $user->extra['authorizer_appid'] ?? null);
        self::assertSame('AUTH_TOK', $user->extra['authorizer_access_token'] ?? null);
    }
}
