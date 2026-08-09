<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatWork;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;
use Kode\MiniApp\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 企业微信 Auth::session() 登录自动托管 session_key 集成测试
 *
 * 企业微信 session() 需先取 access_token（gettoken）再调 jscode2session，
 * 桩按 URL 路由两条请求。
 */
class AuthSessionKeyStoreTest extends TestCase
{
    /**
     * @param array<string, mixed> $sessionBody
     */
    private function buildKernel(ArrayCache $cache, array $sessionBody): Kernel
    {
        $stub = new class ($sessionBody) implements HttpClientInterface {
            /**
             * @param array<string, mixed> $sessionBody
             */
            public function __construct(private array $sessionBody)
            {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                if (str_contains($uri, 'gettoken')) {
                    return $this->respond([
                        'errcode'     => 0,
                        'errmsg'      => 'ok',
                        'access_token' => 'tok-xxxx',
                        'expires_in'   => 7200,
                    ]);
                }

                if (str_contains($uri, 'jscode2session')) {
                    return $this->respond($this->sessionBody);
                }

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
                return $this->respond([]);
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

        return new Kernel([
            'wechat_work' => [
                'corp_id'  => 'corp123',
                'secret'   => 'app-secret',
                'agent_id' => '1000002',
                'cache'    => $cache,
            ],
        ], $stub);
    }

    public function testSessionStoresSessionKeyOnSuccess(): void
    {
        $cache   = new ArrayCache();
        $kernel  = $this->buildKernel($cache, [
            'errcode'     => 0,
            'errmsg'      => 'ok',
            'session_key' => 'sk-encrypted',
            'openid'      => 'openid-abc',
            'userid'      => 'user-abc',
            'expires_in'  => 7200,
        ]);

        $app = $kernel->wechatWork()->app();
        \assert($app instanceof WechatWorkApp);

        $result = $app->auth()->session('js-code');

        self::assertSame('sk-encrypted', $result['session_key']);
        self::assertSame('openid-abc', $result['openid']);
        // 登录成功后 session_key 被自动托管
        self::assertSame('sk-encrypted', SessionKeyManager::for($app->config())->get('openid-abc'));
    }

    public function testSessionDoesNotStoreWhenSessionKeyMissing(): void
    {
        $cache   = new ArrayCache();
        $kernel  = $this->buildKernel($cache, [
            'errcode'    => 0,
            'errmsg'     => 'ok',
            'openid'     => 'openid-abc',
            'expires_in' => 7200,
        ]);

        $app = $kernel->wechatWork()->app();
        \assert($app instanceof WechatWorkApp);

        $result = $app->auth()->session('js-code');

        self::assertArrayNotHasKey('session_key', $result);
        // 无 session_key 时不应写入缓存
        self::assertNull(SessionKeyManager::for($app->config())->get('openid-abc'));
    }
}
