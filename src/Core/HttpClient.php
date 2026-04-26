<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Exceptions\HttpException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP 客户端统一封装
 * 支持中间件、重试、日志、超时配置
 */
final class HttpClient implements HttpClientInterface
{
    private Client $client;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->client = $this->createClient();
    }

    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    public function put(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    public function patch(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    public function delete(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->request('POST', $uri, [
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'json'    => $data,
        ]);
    }

    public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
    {
        $multipart = [];
        foreach ($form as $key => $value) {
            $multipart[] = ['name' => $key, 'contents' => $value];
        }
        $multipart[] = [
            'name'     => $field,
            'contents' => fopen($filePath, 'r'),
            'filename' => basename($filePath),
        ];

        return $this->request('POST', $uri, ['multipart' => $multipart]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        try {
            $response = $this->client->request($method, $uri, $options);
            $this->logger->debug("HTTP {$method} {$uri}", ['status' => $response->getStatusCode()]);

            return $response;
        } catch (RequestException $e) {
            $this->logger->error("HTTP {$method} {$uri} 失败", ['error' => $e->getMessage()]);
            throw new HttpException("请求失败: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    private function createClient(): Client
    {
        $stack = HandlerStack::create();

        // 日志中间件
        $stack->push(Middleware::tap(
            function (RequestInterface $request): void {
                $this->logger->debug('发送请求', [
                    'method'  => $request->getMethod(),
                    'uri'     => (string) $request->getUri(),
                    'headers' => $request->getHeaders(),
                ]);
            }
        ));

        // 重试中间件（最多 3 次）
        $stack->push(Middleware::retry(
            function (int $retries, ?RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $exception = null): bool {
                return $retries < 3 && ($exception instanceof RequestException || ($response && $response->getStatusCode() >= 500));
            },
            function (int $retries): int {
                return 1000 * (2 ** $retries); // 指数退避
            }
        ));

        $defaultConfig = [
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => true,
            'handler'         => $stack,
        ];

        return new Client(array_merge($defaultConfig, $this->config));
    }
}
