<?php

declare(strict_types=1);

namespace Kode\MiniApp\Exceptions;

use Kode\MiniApp\Contracts\Platform;

/**
 * 平台开放接口业务异常
 *
 * 与 HttpException（网络层失败）区分：本异常表示 HTTP 请求成功返回，
 * 但平台在响应体中返回了业务错误码（errcode / err_no / errno / code / error）。
 *
 * 用法：
 *   try {
 *       $app->auth()->token();
 *   } catch (ApiException $e) {
 *       if ($e->isTokenInvalid()) { $app->auth()->refreshToken(); }
 *       if ($e->isRetryable())    { // 稍后重试 }
 *       $e->errorCode();  // 40001
 *       $e->platform();   // Platform::Wechat
 *       $e->payload();    // 原始响应数组
 *   }
 */
class ApiException extends MiniAppException
{
    /**
     * 令牌失效类错误码（跨平台合集）
     *
     * - 微信 / QQ / 企业微信：40001 40014 42001 41001 42009
     * - 钉钉：88 33001 40014
     * - 飞书：99991663 99991661 99991664
     * - 抖音：40018 40019
     *
     * @var array<int, int|string>
     */
    public const array TOKEN_INVALID_CODES = [
        40001, 40014, 41001, 42001, 42009, 40018, 40019,
        88, 33001,
        99991661, 99991663, 99991664,
        'invalid_access_token', 'expired_access_token',
    ];

    /**
     * 频率限制类错误码
     *
     * - 微信：45009（接口调用超限）、45011（频率限制）、-1（系统繁忙）
     * - 企业微信：45033、301019
     * - 飞书：99991400
     *
     * @var array<int, int|string>
     */
    public const array RATE_LIMITED_CODES = [
        -1, 45009, 45011, 45033, 301019, 99991400, 9498,
    ];

    /**
     * 可安全重试的错误码（系统繁忙 / 服务暂不可用）
     *
     * @var array<int, int|string>
     */
    public const array RETRYABLE_CODES = [
        -1, -2, 45009, 45011, 45033, 301019, 99991400, 9498, 40001,
    ];

    /**
     * @param array<string, mixed> $payload 平台返回的完整响应体
     */
    public function __construct(
        string $message,
        private readonly int|string $errorCode = 0,
        private readonly ?Platform $platform = null,
        private readonly array $payload = [],
        private readonly ?string $action = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            self::format($message, $errorCode, $platform, $action),
            is_int($errorCode) ? $errorCode : 0,
            $previous
        );
    }

    /**
     * 平台原始错误码（微信为 int，OAuth 风格平台可能为 string）
     */
    public function errorCode(): int|string
    {
        return $this->errorCode;
    }

    /**
     * 归属平台
     */
    public function platform(): ?Platform
    {
        return $this->platform;
    }

    /**
     * 业务动作描述（如「微信登录」）
     */
    public function action(): ?string
    {
        return $this->action;
    }

    /**
     * 平台返回的完整响应体
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * 是否为令牌失效（业务侧可据此清缓存后重试）
     */
    public function isTokenInvalid(): bool
    {
        return in_array($this->errorCode, self::TOKEN_INVALID_CODES, true);
    }

    /**
     * 是否触发平台频率限制
     */
    public function isRateLimited(): bool
    {
        return in_array($this->errorCode, self::RATE_LIMITED_CODES, true);
    }

    /**
     * 是否属于可重试错误
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE_CODES, true);
    }

    private static function format(
        string $message,
        int|string $errorCode,
        ?Platform $platform,
        ?string $action,
    ): string {
        $prefix = $platform !== null ? "[{$platform->label()}] " : '';
        $scene  = $action !== null && $action !== '' ? "{$action}失败: " : '';

        return "{$prefix}{$scene}[{$errorCode}] {$message}";
    }
}
