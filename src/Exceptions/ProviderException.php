<?php

declare(strict_types=1);

namespace Kode\MiniApp\Exceptions;

use Kode\MiniApp\Contracts\Platform;

/**
 * Provider 异常
 */
class ProviderException extends PlatformException
{
    public function __construct(
        string $message,
        Platform $platform,
        private readonly string $provider,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct("[{$provider}] {$message}", $platform, $code, $previous);
    }

    public function provider(): string
    {
        return $this->provider;
    }
}
