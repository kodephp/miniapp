<?php

declare(strict_types=1);

namespace Kode\MiniApp\Exceptions;

use Kode\MiniApp\Contracts\Platform;

/**
 * 平台级异常
 */
class PlatformException extends MiniAppException
{
    public function __construct(
        string $message,
        private readonly Platform $platform,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct("[{$platform->label()}] {$message}", $code, $previous);
    }

    public function platform(): Platform
    {
        return $this->platform;
    }
}
