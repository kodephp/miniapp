<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Wechat;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 微信 Auth 令牌缓存集成测试
 *
 * 验证 access_token 命中 PSR-16 缓存、刷新强制重取、forget 清除缓存。
 */
class AuthTokenCacheTest extends TestCase
{
    private function buildKernel(ArrayCache $cache, \stdClass $counter): Kernel
    {
        $stub = new class ($counter) implements HttpClientInterface {
            public function __construct(
                private \stdClass $counter,
            ) {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                $this->counter->calls++;
                $token = 'TOK_' . $this->counter->calls;

                return $this->respond(['access_token' => $token, 'expires_in' => 7200]);
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

    public function testTokenCachingAndRefresh(): void
    {
        $cache = new ArrayCache();
        $counter = new \stdClass();
        $counter->calls = 0;

        /** @var WechatApp $app */
        $app = $this->buildKernel($cache, $counter)->wechat()->app();

        $first = $app->auth()->token();
        $second = $app->auth()->token();

        self::assertSame('TOK_1', $first);
        self::assertSame('TOK_1', $second, '第二次应命中缓存，不重新请求');
        self::assertSame(1, $counter->calls);

        $refreshed = $app->auth()->refreshToken();
        self::assertSame('TOK_2', $refreshed, '刷新应强制重新换取');
        self::assertSame(2, $counter->calls);

        $afterRefresh = $app->auth()->token();
        self::assertSame('TOK_2', $afterRefresh, '刷新后缓存应保持');
        self::assertSame(2, $counter->calls);
    }

    public function testForgetClearsCache(): void
    {
        $cache = new ArrayCache();
        $counter = new \stdClass();
        $counter->calls = 0;

        /** @var WechatApp $app */
        $app = $this->buildKernel($cache, $counter)->wechat()->app();

        $app->auth()->token();
        $app->auth()->forgetToken();
        $again = $app->auth()->token();

        self::assertSame('TOK_2', $again, '清除后应重新换取');
        self::assertSame(2, $counter->calls);
    }
}
