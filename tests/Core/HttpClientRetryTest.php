<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\HttpClient;
use Kode\MiniApp\Core\RetryPolicy;
use Kode\MiniApp\Tests\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

/**
 * HttpClient 能力测试：json 解析、禁用重试、Guzzle 重试集成
 */
class HttpClientRetryTest extends TestCase
{
    public function testJsonReturnsApiResponse(): void
    {
        $calls = 0;
        $handler = function (RequestInterface $request, array $options) use (&$calls): Response {
            $calls++;

            return new Response(200, [], (string) json_encode(['errcode' => 0, 'openid' => 'o1']));
        };

        $client = new HttpClient(['retry' => false, 'handler' => $handler], new NullLogger());
        $resp = $client->json('GET', 'https://api.weixin.qq.com/x', [], Platform::Wechat);

        self::assertInstanceOf(ApiResponse::class, $resp);
        self::assertTrue($resp->isSuccessful());
        self::assertSame('o1', $resp->get('openid'));
        self::assertSame(1, $calls);
    }

    public function testRetryDisabledDoesSingleCallOnError(): void
    {
        $calls = 0;
        $handler = function (RequestInterface $request, array $options) use (&$calls): Response {
            $calls++;

            return new Response(503);
        };

        $client = new HttpClient(['retry' => false, 'handler' => $handler], new NullLogger());
        $resp = $client->get('https://api.weixin.qq.com/x');

        self::assertSame(503, $resp->getStatusCode());
        self::assertSame(1, $calls, '禁用重试时不应重复请求');
    }

    public function testRetryPolicyReflectedFromConfig(): void
    {
        $client = new HttpClient(['retry' => ['times' => 7, 'base_delay' => 150]], new NullLogger());
        self::assertSame(7, $client->retryPolicy()->times);
        self::assertSame(150, $client->retryPolicy()->baseDelay);

        $disabled = new HttpClient(['retry' => false], new NullLogger());
        self::assertSame(0, $disabled->retryPolicy()->times);
    }

    public function testWithConfigImmutable(): void
    {
        $base = new HttpClient(['retry' => false], new NullLogger());
        $derived = $base->withConfig(['retry' => ['times' => 2]]);

        self::assertNotSame($base, $derived);
        self::assertSame(0, $base->retryPolicy()->times);
        self::assertSame(2, $derived->retryPolicy()->times);
    }

    public function testGuzzleRetryIntegration(): void
    {
        // 501 不可重试，503 可重试；首个 503 应触发一次重试拿到 200
        $mock = new MockHandler([
            new Response(503),
            new Response(200, [], (string) json_encode(['ok' => true])),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::retry(
            function (int $retries, ?RequestInterface $request = null, ?Response $response = null): bool {
                unset($request);
                if ($response === null) {
                    return false;
                }

                return in_array($response->getStatusCode(), RetryPolicy::DEFAULT_RETRY_STATUS, true);
            },
            fn (): int => 1,
        ));

        $client = new Client(['handler' => $stack]);
        $response = $client->get('https://example.com/x');

        self::assertSame(200, $response->getStatusCode());
        // 若未重试，第一个 503 会被 http_errors 抛出；到达 200 即证明重试生效
    }
}
