<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\PayAdapter;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * QQ 支付适配器（Union 层接线）测试
 *
 * 验证 `Union::qq()->pay()->unifiedOrder([...])` 正确分派到 `QqApp::pay()`。
 * HTTP 层以匿名桩替换，避免真实请求，同时验证底层 Pay 模块被调用。
 */
final class QqPayAdapterTest extends TestCase
{
    private function buildUnion(string $xmlBody, \stdClass $capture): Union
    {
        $stub = new class ($xmlBody, $capture) implements HttpClientInterface {
            public function __construct(
                private string $xmlBody,
                private \stdClass $capture,
            ) {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond('');
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                $this->capture->uri  = $uri;
                $this->capture->body = (string) ($options['body'] ?? '');

                return $this->respond($this->xmlBody);
            }

            public function put(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond('');
            }

            public function patch(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond('');
            }

            public function delete(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond('');
            }

            public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
            {
                return $this->respond('');
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return $this->respond('');
            }

            private function respond(string $body): ResponseInterface
            {
                return new class ($body) implements ResponseInterface {
                    public function __construct(private string $body)
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
                            public function __construct(private string $body)
                            {
                            }

                            public function __toString(): string
                            {
                                return $this->body;
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
                                return $this->body;
                            }

                            public function getMetadata($k = null)
                            {
                                return null;
                            }
                        };
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
                        return '';
                    }

                    public function withBody(\Psr\Http\Message\StreamInterface $body): self
                    {
                        return $this;
                    }
                };
            }
        };

        $kernel = new Kernel(
            [
                'qq' => [
                    'app_id'  => 'qqapp000000000000',
                    'secret'  => 'qq-secret',
                    'mch_id'  => 'qq-mch',
                    'api_key' => 'qq-api-key',
                    'cache'   => new ArrayCache(),
                ],
            ],
            $stub,
        );
        $kernel->union();

        return $kernel->union();
    }

    public function testQqPayAdapterIsResolved(): void
    {
        $union = $this->buildUnion('', new \stdClass());
        $pay   = $union->qq()->pay();

        self::assertInstanceOf(PayAdapter::class, $pay);
        self::assertSame(Channel::Qq, $pay->channel());
    }

    public function testQqPayUnifiedOrderDispatchesToProvider(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildUnion(
            '<xml><return_code><![CDATA[SUCCESS]]></return_code>' .
            '<prepay_id><![CDATA[prepay_abc123]]></prepay_id></xml>',
            $capture,
        );

        $result = $union->qq()->pay()->unifiedOrder([
            'out_trade_no' => 'ORDER_1',
            'body'         => '商品',
            'total_fee'    => 100,
            'openid'       => 'OPENID_1',
        ]);

        // 底层 Qq Modules\Pay 已被调用（命中 unipay 下单地址）
        self::assertStringContainsString(
            'unipay.qq.com',
            (string) $capture->uri,
        );
        // XML 请求体已携带业务参数
        self::assertStringContainsString('ORDER_1', (string) $capture->body);

        // 返回结构由底层 Pay 模块 XML 解析得到
        self::assertSame('SUCCESS', $result['return_code'] ?? null);
        self::assertSame('prepay_abc123', $result['prepay_id'] ?? null);
    }

    public function testQqPayViaStaticFacade(): void
    {
        $union = $this->buildUnion('', new \stdClass());
        // 静态门面同样可用
        self::assertSame(Channel::Qq, Union::qq()->pay()->channel());
    }
}
