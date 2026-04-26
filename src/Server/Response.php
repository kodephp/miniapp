<?php

declare(strict_types=1);

namespace Kode\MiniApp\Server;

/**
 * 服务端响应对象
 */
readonly class Response
{
    public function __construct(
        private string $content,
        private int $statusCode = 200,
        private array $headers = [],
    ) {
    }

    public function content(): string
    {
        return $this->content;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * 发送响应
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }
        echo $this->content;
    }
}
