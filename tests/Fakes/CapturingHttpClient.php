<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Fakes;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 捕获最近一次请求的 HttpClient 桩
 *
 * 用于需要断言「请求头 / 请求体 / 方法」的场景（如微信 V3 签名头校验）。
 * 返回体由 {@see stub()} 预设；未预设时返回空 body。
 */
final class CapturingHttpClient implements HttpClientInterface
{
    /**
     * @var array{method:string,uri:string,body:string,headers:array<string,string>}|null
     */
    private ?array $last = null;

    /** @var array<string, mixed> */
    private array $stub = [];

    /**
     * @param array<string, mixed> $body
     */
    public function stub(array $body): void
    {
        $this->stub = $body;
    }

    /**
     * @return array{method:string,uri:string,body:string,headers:array<string,string>}
     */
    public function last(): array
    {
        \assert($this->last !== null);

        return $this->last;
    }

    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->capture('GET', $uri, '', $options);
    }

    public function post(string $uri, array $options = []): ResponseInterface
    {
        $body = (string) ($options['body'] ?? '');

        return $this->capture('POST', $uri, $body, $options);
    }

    public function put(string $uri, array $options = []): ResponseInterface
    {
        return $this->capture('PUT', $uri, (string) ($options['body'] ?? ''), $options);
    }

    public function patch(string $uri, array $options = []): ResponseInterface
    {
        return $this->capture('PATCH', $uri, (string) ($options['body'] ?? ''), $options);
    }

    public function delete(string $uri, array $options = []): ResponseInterface
    {
        return $this->capture('DELETE', $uri, '', $options);
    }

    public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->capture('POST', $uri, (string) json_encode($data), ['headers' => $headers]);
    }

    public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
    {
        return $this->capture('POST', $uri, '', []);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function capture(string $method, string $uri, string $body, array $options): ResponseInterface
    {
        $headers = [];
        foreach (($options['headers'] ?? []) as $k => $v) {
            $headers[(string) $k] = (string) $v;
        }

        $this->last = ['method' => $method, 'uri' => $uri, 'body' => $body, 'headers' => $headers];

        return new FakeResponse($this->stub);
    }
}
