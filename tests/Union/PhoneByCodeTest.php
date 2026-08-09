<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use InvalidArgumentException;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Union::phoneByCode() 统一 code 换手机号测试
 *
 * 覆盖微信小程序分派成功、openid 透传，以及不支持渠道的显式抛错。
 */
class PhoneByCodeTest extends TestCase
{
    private function buildKernel(\stdClass $capture): Kernel
    {
        $stub = new class ($capture) implements HttpClientInterface {
            public function __construct(private \stdClass $capture)
            {
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

                return $this->respond([
                    'errcode'    => 0,
                    'errmsg'     => 'ok',
                    'phone_info' => [
                        'phoneNumber'     => '+8613800138000',
                        'purePhoneNumber' => '13800138000',
                        'countryCode'     => '86',
                        'watermark'       => ['timestamp' => 1637744274, 'appid' => 'wx123'],
                    ],
                ]);
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
                    'cache'  => new ArrayCache(),
                ],
            ],
            $stub,
        );
    }

    public function testUnionPhoneByCodeWechatMini(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        $info = $union->phoneByCode(Channel::WechatMini, 'phone-code-1');

        self::assertSame('+8613800138000', $info['phoneNumber']);
        self::assertSame('13800138000', $info['purePhoneNumber']);
        self::assertSame(['code' => 'phone-code-1'], $capture->data);
    }

    public function testUnionPhoneByCodePassesOpenId(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        $union->phoneByCode(Channel::WechatMini, 'phone-code-1', 'OPENID_1');

        self::assertSame(['code' => 'phone-code-1', 'openid' => 'OPENID_1'], $capture->data);
    }

    public function testUnsupportedChannelThrows(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 code 换取手机号');

        $union->phoneByCode(Channel::BaiduMini, 'phone-code-1');
    }

    public function testDouyinChannelThrows(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        // 抖音同类接口返回 RSA 密文（需应用私钥解密），暂不纳入统一入口。
        $this->expectException(InvalidArgumentException::class);

        $union->phoneByCode(Channel::DouyinMini, 'phone-code-1');
    }
}
