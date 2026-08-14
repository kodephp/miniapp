<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Qq;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Qq\Modules\Pay;
use Kode\MiniApp\Providers\Qq\QqApp;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * QQ 支付模块（orderQuery / closeOrder / refund）Provider 级测试
 *
 * 锁定此前零覆盖的三个支付衍生方法：验证各自命中正确端点、
 * 请求体携带业务参数且签名与 {@see Pay::sign} 一致（签名一致性锁定）、
 * 并能正确解析 XML 响应。
 *
 * 注：HTTP 层以 XML 返回桩替换——canonical FakeHttpClient/FakeResponse 仅支持 JSON body，
 * 不适用于 QQ 支付这种 XML 端点，故沿用 QqPayAdapterTest 的内联客户端模式。
 */
final class PayModuleTest extends TestCase
{
    private function buildApp(string $xmlBody, \stdClass $capture): QqApp
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

        $app = $kernel->qq()->app();
        self::assertInstanceOf(QqApp::class, $app);

        return $app;
    }

    /**
     * 将捕获的请求 XML body 解析回数组
     *
     * @return array<string, mixed>
     */
    private function parseRequest(\stdClass $capture): array
    {
        $xml = simplexml_load_string($capture->body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            return [];
        }

        $decoded = json_decode((string) json_encode($xml), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function testOrderQueryDispatchesAndSigns(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp(
            '<xml>'
            . '<return_code><![CDATA[SUCCESS]]></return_code>'
            . '<result_code><![CDATA[SUCCESS]]></result_code>'
            . '<out_trade_no><![CDATA[ORDER_1]]></out_trade_no>'
            . '<trade_state><![CDATA[SUCCESS]]></trade_state>'
            . '</xml>',
            $capture,
        );

        $result = $app->pay()->orderQuery('ORDER_1');

        // 命中正确端点
        self::assertStringContainsString('/145/minipay/orderquery', (string) $capture->uri);
        // 请求体携带业务参数
        self::assertStringContainsString('ORDER_1', (string) $capture->body);
        // XML 响应被正确解析
        self::assertSame('SUCCESS', $result['result_code'] ?? null);
        self::assertSame('ORDER_1', $result['out_trade_no'] ?? null);

        // 签名一致性：用请求体重新计算签名须与携带的 sign 一致
        $request = $this->parseRequest($capture);
        self::assertSame(Pay::sign($request, 'qq-api-key'), $request['sign'] ?? null);
    }

    public function testCloseOrderDispatchesAndSigns(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp(
            '<xml>'
            . '<return_code><![CDATA[SUCCESS]]></return_code>'
            . '<result_code><![CDATA[SUCCESS]]></result_code>'
            . '</xml>',
            $capture,
        );

        $result = $app->pay()->closeOrder('ORDER_2');

        self::assertStringContainsString('/145/minipay/closeorder', (string) $capture->uri);
        self::assertStringContainsString('ORDER_2', (string) $capture->body);
        self::assertSame('SUCCESS', $result['result_code'] ?? null);

        $request = $this->parseRequest($capture);
        self::assertSame(Pay::sign($request, 'qq-api-key'), $request['sign'] ?? null);
    }

    public function testRefundDispatchesAndSigns(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp(
            '<xml>'
            . '<return_code><![CDATA[SUCCESS]]></return_code>'
            . '<result_code><![CDATA[SUCCESS]]></result_code>'
            . '<refund_id><![CDATA[REFUND_1]]></refund_id>'
            . '</xml>',
            $capture,
        );

        $result = $app->pay()->refund([
            'out_trade_no'    => 'ORDER_3',
            'out_refund_no'   => 'REFUND_3',
            'total_fee'       => 100,
            'refund_fee'      => 100,
            'op_user_id'      => 'qq-mch',
        ]);

        self::assertStringContainsString('/145/minipay/refund', (string) $capture->uri);
        self::assertStringContainsString('ORDER_3', (string) $capture->body);
        self::assertSame('SUCCESS', $result['result_code'] ?? null);
        self::assertSame('REFUND_1', $result['refund_id'] ?? null);

        // refund 业务参数须进入请求体
        $request = $this->parseRequest($capture);
        self::assertSame('REFUND_3', $request['out_refund_no'] ?? null);
        self::assertSame(Pay::sign($request, 'qq-api-key'), $request['sign'] ?? null);
    }
}
