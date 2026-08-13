<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Fakes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 测试用 HTTP 响应桩（JSON body）
 *
 * 集中实现 PSR-7 ResponseInterface / StreamInterface，避免各测试文件重复内联样板。
 */
final class FakeResponse implements ResponseInterface
{
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
        $json = (string) json_encode($this->body);

        return new class ($json) implements StreamInterface {
            public function __construct(private string $content)
            {
            }

            public function __toString(): string
            {
                return $this->content;
            }

            public function close(): void
            {
            }

            public function detach()
            {
                return null;
            }

            public function getSize(): int
            {
                return strlen($this->content);
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
                return $this->content;
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
}
