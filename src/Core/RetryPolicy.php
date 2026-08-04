<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP 重试策略
 *
 * 支持：最大次数、指数退避、随机抖动（避免惊群）、可重试状态码白名单、
 * 以及遵循服务端 Retry-After 响应头（429 / 503 常见）。
 *
 * 用法：
 *   $policy = RetryPolicy::fromArray(['times' => 5, 'base_delay' => 100]);
 *   new HttpClient(['retry' => ['times' => 5]]);
 *   new HttpClient(['retry' => false]); // 关闭重试
 */
final readonly class RetryPolicy
{
    /**
     * 默认可重试的 HTTP 状态码
     *
     * @var array<int, int>
     */
    public const array DEFAULT_RETRY_STATUS = [408, 429, 500, 502, 503, 504];

    /**
     * 单次退避上限（毫秒），防止 Retry-After 过大导致长时间阻塞
     */
    public const int RETRY_AFTER_CEILING = 30_000;

    /**
     * @param int              $times             最大重试次数（不含首次请求）
     * @param int              $baseDelay         退避基数（毫秒）
     * @param int              $maxDelay          单次退避上限（毫秒）
     * @param bool             $jitter            是否加入随机抖动
     * @param array<int, int>  $retryStatus       需要重试的 HTTP 状态码
     * @param bool             $respectRetryAfter 是否遵循 Retry-After 响应头
     */
    public function __construct(
        public int $times = 3,
        public int $baseDelay = 200,
        public int $maxDelay = 5_000,
        public bool $jitter = true,
        public array $retryStatus = self::DEFAULT_RETRY_STATUS,
        public bool $respectRetryAfter = true,
    ) {
    }

    /**
     * 从配置数组构造
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $status = $config['status'] ?? self::DEFAULT_RETRY_STATUS;

        return new self(
            times: max(0, (int) ($config['times'] ?? 3)),
            baseDelay: max(1, (int) ($config['base_delay'] ?? 200)),
            maxDelay: max(1, (int) ($config['max_delay'] ?? 5_000)),
            jitter: (bool) ($config['jitter'] ?? true),
            retryStatus: is_array($status) ? array_values(array_map(intval(...), $status)) : self::DEFAULT_RETRY_STATUS,
            respectRetryAfter: (bool) ($config['respect_retry_after'] ?? true),
        );
    }

    /**
     * 关闭重试
     */
    public static function disabled(): self
    {
        return new self(times: 0);
    }

    /**
     * 是否需要重试
     *
     * @param int $retries 已重试次数
     */
    public function shouldRetry(int $retries, ?ResponseInterface $response = null, ?\Throwable $exception = null): bool
    {
        if ($retries >= $this->times) {
            return false;
        }

        // 连接层异常（超时、DNS、断连）一律重试
        if ($exception instanceof ConnectException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $exceptionResponse = $exception->getResponse();

            return $exceptionResponse === null
                || in_array($exceptionResponse->getStatusCode(), $this->retryStatus, true);
        }

        if ($response !== null) {
            return in_array($response->getStatusCode(), $this->retryStatus, true);
        }

        return false;
    }

    /**
     * 计算下一次重试的等待时长（毫秒）
     *
     * @param int $retries 已重试次数（从 0 开始）
     */
    public function delayFor(int $retries, ?ResponseInterface $response = null): int
    {
        if ($this->respectRetryAfter && $response !== null) {
            $retryAfter = $this->parseRetryAfter($response);
            if ($retryAfter !== null) {
                return min($retryAfter, self::RETRY_AFTER_CEILING);
            }
        }

        $delay = (int) min($this->baseDelay * (2 ** $retries), $this->maxDelay);

        if ($this->jitter) {
            // 全抖动：[delay/2, delay]，避免多进程同时重试造成尖峰
            $half = max(1, intdiv($delay, 2));
            $lo   = min($half, $delay);
            $hi   = max($half, $delay);
            $delay = random_int($lo, $hi);
        }

        return $delay;
    }

    /**
     * 解析 Retry-After 响应头，返回毫秒；不存在或非法时返回 null
     */
    private function parseRetryAfter(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');
        if ($header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return max(0, (int) round((float) $header * 1000));
        }

        $timestamp = strtotime($header);
        if ($timestamp === false) {
            return null;
        }

        return max(0, ($timestamp - time()) * 1000);
    }
}
