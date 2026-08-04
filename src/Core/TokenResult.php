<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

/**
 * 令牌刷新结果
 *
 * TokenManager 的 resolver 回调需返回本对象，用于同时携带令牌值与有效期。
 *
 * 用法：
 *   return new TokenResult($data['access_token'], (int) $data['expires_in']);
 */
final readonly class TokenResult
{
    /**
     * 平台未返回有效期时使用的默认值（秒）
     */
    public const int DEFAULT_EXPIRES_IN = 7200;

    /**
     * @param mixed $value     令牌值（通常是 string，百度等平台可为完整数组）
     * @param int   $expiresIn 有效期（秒）
     */
    public function __construct(
        public mixed $value,
        public int $expiresIn = self::DEFAULT_EXPIRES_IN,
    ) {
    }
}
