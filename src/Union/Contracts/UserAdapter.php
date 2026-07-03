<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 用户资料 / 会员信息适配器接口
 *
 * 登录成功（拿到 openId / unionId）后，
 * 可通过此接口获取用户详细资料。
 */
interface UserAdapter
{
    public function channel(): Channel;

    /**
     * 获取用户资料
     *
     * @param array<string, mixed> $payload
     */
    public function profile(string $openId, array $payload = []): UnionUser;
}
