<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * QQ 统一聚合类
 *
 * 用法：
 *   $user = Union::qq()->login('code');    // QQ 登录
 *   $order = Union::qq()->pay()->unifiedOrder([...]);
 */
final class QqUnion extends PlatformUnion
{
    #[\Override]
    public function platform(): string
    {
        return 'qq';
    }

    /**
     * QQ 登录
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
            'mini' => Channel::Qq,
        ];
    }

    #[\Override]
    protected function defaultChannel(): Channel
    {
        return Channel::Qq;
    }

    #[\Override]
    protected function defaultPayChannel(): Channel
    {
        return Channel::Qq;
    }
}
