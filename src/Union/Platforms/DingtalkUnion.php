<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 钉钉统一聚合类
 *
 * 用法：
 *   $user = Union::dingtalk()->login('code');   // 钉钉登录
 *   $user = Union::dingtalk()->mini('code');    // 钉钉小程序（默认 mini 场景）
 */
final class DingtalkUnion extends PlatformUnion
{
    public function platform(): string
    {
        return 'dingtalk';
    }

    /**
     * 钉钉登录
     */
    public function mini(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mini');
    }

    /**
     * @return array<string, Channel>
     */
    protected function sceneMap(): array
    {
        return [
            'mini' => Channel::Dingtalk,
        ];
    }

    protected function defaultChannel(): Channel
    {
        return Channel::Dingtalk;
    }

    protected function defaultPayChannel(): Channel
    {
        return Channel::Dingtalk;
    }
}
