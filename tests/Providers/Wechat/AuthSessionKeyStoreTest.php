<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Wechat;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 微信 Auth::session() 登录自动托管 session_key 集成测试
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
                return $this->respond($this->sessionBody);
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

        return new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx123',
                    'secret' => 's3cr3t',
                    'cache'  => $cache,
                ],
            ],
            $stub,
        );
    }

    public function testSessionAutoStoresSessionKey(): void
    {
        $cache = new ArrayCache();

        /** @var WechatApp $app */
        $app = $this->buildKernel($cache, [
            'openid'      => 'OPENID_1',
            'session_key' => 'SESSIONKEY_1',
            'unionid'     => 'UNION_1',
            'errcode'     => 0,
            'errmsg'      => 'ok',
        ])->wechat()->app();

        $session = $app->auth()->session('js-code');
        self::assertSame('OPENID_1', $session['openid']);

        // 登录后 session_key 应已自动托管
        self::assertSame(
            'SESSIONKEY_1',
            SessionKeyManager::for($app->config())->get('OPENID_1'),
            '登录成功应自动托管 session_key 供后续解密复用',
        );
    }

    public function testSessionWithoutSessionKeyIsNotStored(): void
    {
        $cache = new ArrayCache();

        /** @var WechatApp $app */
        $app = $this->buildKernel($cache, [
            'openid'  => 'OPENID_2',
            'errcode' => 0,
            'errmsg'  => 'ok',
        ])->wechat()->app();

        $app->auth()->session('js-code');

        self::assertNull(
            SessionKeyManager::for($app->config())->get('OPENID_2'),
            '响应缺少 session_key 时不应写入缓存',
        );
    }
}
