<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 抖音统一聚合类
 *
 * 用法：
 *   $user = Union::douyin()->mini('code');   // 抖音小程序
 *   $user = Union::douyin()->mp('code');     // 抖音头条号
 */
final class DouyinUnion extends PlatformUnion
{
    public function platform(): string
    {
        return 'douyin';
    }

    /**
     * 抖音小程序登录
     */
    public function mini(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mini');
    }

    /**
     * 抖音头条号登录
     */
    public function mp(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mp');
    }

    /**
     * @return array<string, Channel>
     */
    protected function sceneMap(): array
    {
        return [
            'mini' => Channel::DouyinMini,
            'mp'   => Channel::DouyinMp,
        ];
    }

    protected function defaultChannel(): Channel
    {
        return Channel::DouyinMini;
    }

    protected function defaultPayChannel(): Channel
    {
        return Channel::DouyinMini;
    }
}
