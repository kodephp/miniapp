<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Qq;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * QQ 配置
 */
readonly class QqConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::Qq, $data);
    }
}
