<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Exceptions\HttpException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP 客户端统一封装
 *
 * 能力：
 * - 可配置重试（次数 / 指数退避 / 随机抖动 / 状态码白名单 / Retry-After）
 * - 日志自动脱敏（secret、access_token、sign 等一律掩码）
 * - 自定义中间件注入
 * - 慢请求告警与耗时统计
 *
 * 用法：
 *   new HttpClient([
 *       'timeout' => 15,
 *       'retry'   => ['times' => 5, 'base_delay' => 100],   // 或 'retry' => false 关闭
 *       'slow_threshold' => 2.0,                            // 秒
 *       'middlewares'    => [$myMiddleware],
 *   ], $logger);
 */
final class HttpClient implements HttpClientInterface
{
    /**
     * 慢请求默认阈值（秒）
     */
    public const float DEFAULT_SLOW_THRESHOLD = 3.0;

    /**
     * 非 Guzzle 原生的自定义配置键，创建 Client 前需要剔除
     *
     * @var array<int, string>
     */
    public const array RESERVED_KEYS = ['retry', 'middlewares', 'slow_threshold'];

    private Client $client;

    private readonly RetryPolicy $retryPolicy;

    private readonly float $slowThreshold;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly LoggerInterface $logger = new NullLogger(),
        ?RetryPolicy $retryPolicy = null,
    ) {
        $this->retryPolicy   = $retryPolicy ?? $this->resolveRetryPolicy();
        $this->slowThreshold = (float) ($this->config['slow_threshold'] ?? self::DEFAULT_SLOW_THRESHOLD);
        $this->client        = $this->createClient();
    }

    #[\Override]
    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    #[\Override]
    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    #[\Override]
    public function put(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PUT', $uri, $options);
    }

    #[\Override]
    public function patch(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('PATCH', $uri, $options);
    }

    #[\Override]
    public function delete(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    #[\Override]
    public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->request('POST', $uri, [
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'json'    => $data,
        ]);
    }

    /**
     * @param array<string, mixed> $form
     */
    #[\Override]
    public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
    {
        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            throw new HttpException("上传文件不可读: {$filePath}");
        }

        $multipart = [];
        foreach ($form as $key => $value) {
            $multipart[] = ['name' => (string) $key, 'contents' => $value];
        }
        $multipart[] = [
            'name'     => $field,
            'contents' => $handle,
            'filename' => basename($filePath),
        ];

        return $this->request('POST', $uri, ['multipart' => $multipart]);
    }

    /**
     * 发送请求并直接解析为统一响应对象
     *
     * @param array<string, mixed> $options
     */
    public function json(string $method, string $uri, array $options = [], ?Platform $platform = null): ApiResponse
    {
        return ApiResponse::fromPsr($this->request($method, $uri, $options), $platform);
    }

    /**
     * 当前生效的重试策略
     */
    public function retryPolicy(): RetryPolicy
    {
        return $this->retryPolicy;
    }

    /**
     * 基于当前实例派生一个新配置的客户端（不可变风格）
     *
     * @param array<string, mixed> $config
     */
    public function withConfig(array $config): self
    {
        return new self(array_merge($this->config, $config), $this->logger);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $startedAt = microtime(true);
        $safeUri   = LogSanitizer::uri($uri);

        try {
            $response = $this->client->request($method, $uri, $options);
            $elapsed  = microtime(true) - $startedAt;

            $context = [
                'status'  => $response->getStatusCode(),
                'elapsed' => round($elapsed, 4),
            ];

            if ($elapsed >= $this->slowThreshold) {
                $this->logger->warning("HTTP {$method} {$safeUri} 慢请求", $context);
            } else {
                $this->logger->debug("HTTP {$method} {$safeUri}", $context);
            }

            return $response;
        } catch (RequestException $e) {
            $this->logger->error("HTTP {$method} {$safeUri} 失败", [
                'error'   => $e->getMessage(),
                'status'  => $e->getResponse()?->getStatusCode(),
                'elapsed' => round(microtime(true) - $startedAt, 4),
            ]);

            throw new HttpException("请求失败: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    private function createClient(): Client
    {
        $stack = HandlerStack::create();

        // 重试中间件（放在最内层，先于日志执行）
        if ($this->retryPolicy->times > 0) {
            $stack->push(Middleware::retry(
                $this->retryDecider(),
                fn (int $retries, ?ResponseInterface $response = null): int
                    => $this->retryPolicy->delayFor($retries, $response)
            ));
        }

        // 脱敏日志中间件
        $stack->push(Middleware::tap(
            function (RequestInterface $request): void {
                $this->logger->debug('发送请求', [
                    'method'  => $request->getMethod(),
                    'uri'     => LogSanitizer::uri((string) $request->getUri()),
                    'headers' => LogSanitizer::headers($request->getHeaders()),
                ]);
            }
        ));

        // 业务侧自定义中间件
        foreach ($this->customMiddlewares() as $index => $middleware) {
            $stack->push($middleware, "custom_{$index}");
        }

        $defaultConfig = [
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => true,
        ];

        $clientConfig = array_diff_key(
            array_merge($defaultConfig, $this->config),
            array_flip(self::RESERVED_KEYS)
        );
        $clientConfig['handler'] = $this->config['handler'] ?? $stack;

        return new Client($clientConfig);
    }

    /**
     * @return \Closure(int, ?RequestInterface, ?ResponseInterface, ?\Throwable): bool
     */
    private function retryDecider(): \Closure
    {
        return function (
            int $retries,
            ?RequestInterface $request = null,
            ?ResponseInterface $response = null,
            ?\Throwable $exception = null,
        ): bool {
            $retry = $this->retryPolicy->shouldRetry($retries, $response, $exception);

            if ($retry) {
                $this->logger->warning('HTTP 请求重试', [
                    'attempt' => $retries + 1,
                    'max'     => $this->retryPolicy->times,
                    'uri'     => $request !== null ? LogSanitizer::uri((string) $request->getUri()) : null,
                    'status'  => $response?->getStatusCode(),
                    'error'   => $exception?->getMessage(),
                ]);
            }

            return $retry;
        };
    }

    private function resolveRetryPolicy(): RetryPolicy
    {
        $retry = $this->config['retry'] ?? null;

        if ($retry === false) {
            return RetryPolicy::disabled();
        }

        if ($retry instanceof RetryPolicy) {
            return $retry;
        }

        return is_array($retry) ? RetryPolicy::fromArray($retry) : new RetryPolicy();
    }

    /**
     * @return array<int, callable>
     */
    private function customMiddlewares(): array
    {
        $middlewares = $this->config['middlewares'] ?? [];
        if (!is_array($middlewares)) {
            return [];
        }

        return array_values(array_filter($middlewares, is_callable(...)));
    }
}
