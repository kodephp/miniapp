<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 百度智能小程序统一聚合类
 *
 * 用法：
 *   $user = Union::baidu()->mini('code');   // 百度小程序
 *   $order = Union::baidu()->pay()->unifiedOrder([...]);
 */
final class BaiduUnion extends PlatformUnion
{
    #[\Override]
    public function platform(): string
    {
        return 'baidu';
    }

    /**
     * 百度小程序登录
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
            'mini' => Channel::BaiduMini,
        ];
    }

    #[\Override]
    protected function defaultChannel(): Channel
    {
        return Channel::BaiduMini;
    }

    #[\Override]
    protected function defaultPayChannel(): Channel
    {
        return Channel::BaiduMini;
    }
}
