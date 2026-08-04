<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\TestCase;

/**
 * 平台业务异常 ApiException 测试
 */
class ApiExceptionTest extends TestCase
{
    public function testTokenInvalidClassification(): void
    {
        $e = new ApiException('invalid token', 40001, Platform::Wechat, ['errcode' => 40001]);
        self::assertTrue($e->isTokenInvalid());
        self::assertFalse($e->isRateLimited());
        self::assertSame(40001, $e->errorCode());
        self::assertSame(Platform::Wechat, $e->platform());
        self::assertSame(['errcode' => 40001], $e->payload());
    }

    public function testRateLimitedClassification(): void
    {
        $e = new ApiException('rate limited', 45011, Platform::Wechat);
        self::assertTrue($e->isRateLimited());
        self::assertTrue($e->isRetryable());
    }

    public function testRetryableCodes(): void
    {
        self::assertTrue((new ApiException('', 99991400, Platform::Lark))->isRetryable());
        self::assertFalse((new ApiException('', 40004, Platform::Alipay))->isRetryable());
        self::assertFalse((new ApiException('', 40004, Platform::Alipay))->isTokenInvalid());
    }

    public function testStringErrorCode(): void
    {
        $e = new ApiException('bad', 'invalid_access_token', Platform::Douyin);
        self::assertTrue($e->isTokenInvalid());
        self::assertSame('invalid_access_token', $e->errorCode());
    }

    public function testMessageFormatting(): void
    {
        $e = new ApiException('token invalid', 40001, Platform::Wechat, [], '微信登录');
        self::assertStringContainsString('[微信]', $e->getMessage());
        self::assertStringContainsString('微信登录失败', $e->getMessage());
        self::assertStringContainsString('[40001]', $e->getMessage());
        self::assertStringContainsString('token invalid', $e->getMessage());
    }

    public function testAction(): void
    {
        $e = new ApiException('x', 0, null, [], '刷新令牌');
        self::assertSame('刷新令牌', $e->action());
    }
}
