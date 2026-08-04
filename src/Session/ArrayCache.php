<?php

declare(strict_types=1);

namespace Kode\MiniApp\Session;

use Kode\MiniApp\Core\ArrayCache as CoreArrayCache;

/**
 * 内存 PSR-16 缓存（保留原命名空间以兼容旧代码）
 *
 * @deprecated 自 v1.14.0 起请使用 \Kode\MiniApp\Core\ArrayCache
 */
final class ArrayCache extends CoreArrayCache
{
}
