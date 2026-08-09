<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Wechat;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 微信手机号快速验证（code 换手机号）集成测试
 *
 * 覆盖 wxa/business/getuserphonenumber 的成功换取、openid 透传、
 * 接口错误码、响应结构异常与空 code 防御。
 */
class PhoneTest extends TestCase
{
    /**
     * @param array<string, mixed> $phoneBody
     */
    private function buildApp(array $phoneBody, \stdClass $capture): WechatApp
    {
        $stub = new class ($phoneBody, $capture) implements HttpClientInterface {
            /**
             * @param array<string, mixed> $phoneBody
             */
            public function __construct(
                private array $phoneBody,
                private \stdClass $capture,
            ) {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond(['access_token' => 'TOKEN_1', 'expires_in' => 7200]);
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
                $this->capture->uri  = $uri;
                $this->capture->data = $data;

                return $this->respond($this->phoneBody);
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

        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx123',
                    'secret' => 's3cr3t',
                    'cache'  => new ArrayCache(),
                ],
            ],
            $stub,
        );

        $app = $kernel->wechat()->app();
        \assert($app instanceof WechatApp);

        return $app;
    }

    /**
     * @return array<string, mixed>
     */
    private function successBody(): array
    {
        return [
            'errcode'    => 0,
            'errmsg'     => 'ok',
            'phone_info' => [
                'phoneNumber'     => '+8613800138000',
                'purePhoneNumber' => '13800138000',
                'countryCode'     => '86',
                'watermark'       => ['timestamp' => 1637744274, 'appid' => 'wx123'],
            ],
        ];
    }

    public function testByCodeReturnsPhoneInfo(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $info = $app->phone()->byCode('phone-code-1');

        self::assertSame('+8613800138000', $info['phoneNumber']);
        self::assertSame('13800138000', $info['purePhoneNumber']);
        self::assertSame('86', $info['countryCode']);
        self::assertArrayHasKey('watermark', $info);
    }

    public function testByCodeHitsOfficialEndpointWithToken(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $app->phone()->byCode('phone-code-1');

        self::assertStringContainsString(
            'https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=TOKEN_1',
            (string) $capture->uri,
            '应命中官方 code 换手机号接口并携带 access_token',
        );
        self::assertSame(['code' => 'phone-code-1'], $capture->data);
    }

    public function testByCodePassesOpenIdWhenProvided(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $app->phone()->byCode('phone-code-1', 'OPENID_1');

        self::assertSame(['code' => 'phone-code-1', 'openid' => 'OPENID_1'], $capture->data);
    }

    public function testByCodeOmitsEmptyOpenId(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $app->phone()->byCode('phone-code-1', '');

        self::assertArrayNotHasKey('openid', (array) $capture->data, '空 openid 不应作为参数下发');
    }

    public function testNumberByCodeReturnsPlainString(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        self::assertSame('+8613800138000', $app->phone()->numberByCode('phone-code-1'));
    }

    public function testPureNumberByCodeReturnsPlainString(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        self::assertSame('13800138000', $app->phone()->pureNumberByCode('phone-code-1'));
    }

    public function testEmptyCodeIsRejectedBeforeRequest(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('code 不能为空');

        $app->phone()->byCode('   ');
    }

    public function testApiErrorCodeThrows(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp(['errcode' => 40029, 'errmsg' => 'invalid code'], $capture);

        $this->expectException(ApiException::class);

        $app->phone()->byCode('expired-code');
    }

    public function testMissingPhoneInfoThrows(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp(['errcode' => 0, 'errmsg' => 'ok'], $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('响应缺少 phone_info');

        $app->phone()->byCode('phone-code-1');
    }

    public function testIncompletePhoneInfoThrows(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp([
            'errcode'    => 0,
            'errmsg'     => 'ok',
            'phone_info' => ['phoneNumber' => '+8613800138000'],
        ], $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('purePhoneNumber');

        $app->phone()->byCode('phone-code-1');
    }
}
