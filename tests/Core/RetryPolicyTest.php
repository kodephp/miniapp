<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Kode\MiniApp\Core\RetryPolicy;
use Kode\MiniApp\Tests\TestCase;

/**
 * 重试策略 RetryPolicy 测试
 */
class RetryPolicyTest extends TestCase
{
    public function testFromArrayAndDisabled(): void
    {
        $policy = RetryPolicy::fromArray(['times' => 5, 'base_delay' => 100, 'max_delay' => 1000]);
        self::assertSame(5, $policy->times);
        self::assertSame(100, $policy->baseDelay);
        self::assertSame(1000, $policy->maxDelay);

        self::assertSame(0, RetryPolicy::disabled()->times);
    }

    public function testShouldRetryOnStatus(): void
    {
        $policy = new RetryPolicy(times: 3);
        $resp = new Response(503);
        self::assertTrue($policy->shouldRetry(0, $resp));
        self::assertTrue($policy->shouldRetry(2, $resp));
        // 超过最大重试次数
        self::assertFalse($policy->shouldRetry(3, $resp));
        // 不在白名单的状态码
        self::assertFalse($policy->shouldRetry(0, new Response(404)));
    }

    public function testShouldRetryOnConnectionError(): void
    {
        $policy = new RetryPolicy(times: 3);
        $conn = new ConnectException('timeout', new Request('GET', 'https://x'));
        self::assertTrue($policy->shouldRetry(0, null, $conn));
        // 超过次数后即便连接错误也不再重试
        self::assertFalse($policy->shouldRetry(3, null, $conn));
    }

    public function testDelayForExponentialBackoff(): void
    {
        $policy = new RetryPolicy(times: 5, baseDelay: 100, maxDelay: 100000, jitter: false);
        self::assertSame(100, $policy->delayFor(0));
        self::assertSame(200, $policy->delayFor(1));
        self::assertSame(400, $policy->delayFor(2));
    }

    public function testDelayForRespectsCeiling(): void
    {
        $policy = new RetryPolicy(times: 5, baseDelay: 100, maxDelay: 100000, jitter: false);
        self::assertSame(100000, $policy->delayFor(20));
    }

    public function testDelayForRespectsRetryAfterHeader(): void
    {
        $policy = new RetryPolicy(times: 5, baseDelay: 100, maxDelay: 100000, jitter: false);
        $resp = new Response(429, ['Retry-After' => '2']);
        // 2 秒 => 2000 毫秒，低于 ceiling 30000
        self::assertSame(2000, $policy->delayFor(0, $resp));
    }

    public function testRetryAfterCeiling(): void
    {
        $policy = new RetryPolicy(times: 5, baseDelay: 100, maxDelay: 100000, jitter: false);
        $resp = new Response(503, ['Retry-After' => '999']);
        self::assertSame(RetryPolicy::RETRY_AFTER_CEILING, $policy->delayFor(0, $resp));
    }

    public function testParseRetryAfterInvalid(): void
    {
        $policy = new RetryPolicy();
        $resp = new Response(429, ['Retry-After' => 'garbage']);
        // 非法头回退到指数退避，不再返回 null（respectRetryAfter 仅当能解析才生效）
        $delay = $policy->delayFor(0, $resp);
        self::assertGreaterThan(0, $delay);
    }
}
