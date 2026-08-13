<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 飞书统一聚合类
 *
 * 用法：
 *   $user = Union::lark()->login('code');   // 飞书登录
 *   $user = Union::lark()->mini('code');    // 飞书小程序（默认 mini 场景）
 */
final class LarkUnion extends PlatformUnion
{
    #[\Override]
    public function platform(): string
    {
        return 'lark';
    }

    /**
     * 飞书登录
     */
    public function mini(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mini');
    }

    /**
     * @return array<string, Channel>
     */
    #[\Override]
    protected function sceneMap(): array
    {
        return [
            'mini' => Channel::Lark,
        ];
    }

    #[\Override]
    protected function defaultChannel(): Channel
    {
        return Channel::Lark;
    }

    #[\Override]
    protected function defaultPayChannel(): Channel
    {
        return Channel::Lark;
    }
}
