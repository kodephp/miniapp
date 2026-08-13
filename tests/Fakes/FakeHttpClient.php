<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Fakes;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 测试用 HTTP 客户端桩
 *
 * 集中实现 HttpClientInterface，消除各测试文件内联整套方法签名的样板。
 * 所有方法默认返回 {@see FakeResponse}（默认 body），可按 URI 子串预设响应、统计调用次数。
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<string, mixed> */
    private array $defaultBody;

    private int $postJsonCalls = 0;

    /** @var array<string, array<string, mixed>> */
    private array $responsesByUri = [];

    /**
     * @param array<string, mixed> $defaultBody 默认返回的 JSON body
     */
    public function __construct(array $defaultBody = [])
    {
        $this->defaultBody = $defaultBody;
    }

    public function postJsonCalls(): int
    {
        return $this->postJsonCalls;
    }

    /**
     * 为包含指定子串的 URI 预设响应
     *
     * @param array<string, mixed> $body
     */
    public function stub(string $uriContains, array $body): self
    {
        $this->responsesByUri[$uriContains] = $body;

        return $this;
    }

    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->respond($this->match($uri));
    }

    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->respond($this->match($uri));
    }

    public function put(string $uri, array $options = []): ResponseInterface
    {
        return $this->respond($this->match($uri));
    }

    public function patch(string $uri, array $options = []): ResponseInterface
    {
        return $this->respond($this->match($uri));
    }

    public function delete(string $uri, array $options = []): ResponseInterface
    {
        return $this->respond($this->match($uri));
    }

    public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        $this->postJsonCalls++;

        return $this->respond($this->match($uri));
    }

    public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
    {
        return $this->respond($this->match($uri));
    }

    /**
     * @return array<string, mixed>
     */
    private function match(string $uri): array
    {
        foreach ($this->responsesByUri as $needle => $body) {
            if (str_contains($uri, $needle)) {
                return $body;
            }
        }

        return $this->defaultBody;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function respond(array $body): ResponseInterface
    {
        return new FakeResponse($body);
    }
}
