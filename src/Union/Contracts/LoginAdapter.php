<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 登录 / 鉴权 适配器接口
 *
 * 每个 Channel 对应一个 Adapter 实现，
 * 由 Union 门面统一调度，屏蔽平台差异。
 */
interface LoginAdapter
{
    /**
     * 当前适配器负责的渠道
     */
    public function channel(): Channel;

    /**
     * 认证入口：根据渠道场景完成登录 / 鉴权，返回统一用户对象
     *
     * @param array<string, mixed> $payload 平台原始参数（code / ticket / signature 等）
     */
    public function authenticate(array $payload): UnionUser;
}
