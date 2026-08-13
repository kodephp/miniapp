<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 企业微信统一聚合类
 *
 * 用法：
 *   $user = Union::work()->login('code');          // 企业微信登录
 *   $user = Union::work()->login('code', 'suite'); // 第三方套件登录
 *   $order = Union::work()->pay()->unifiedOrder([...]);
 */
final class WechatWorkUnion extends PlatformUnion
{
    #[\Override]
    public function platform(): string
    {
        return 'wechat_work';
    }

    /**
     * 企业微信登录
     */
    public function mini(string $code): UnionUser
    {
        return $this->loginByCode($code, 'work');
    }

    /**
     * 套件登录（第三方应用）
     */
    public function suite(string $code): UnionUser
    {
        return $this->loginByCode($code, 'work');
    }

    /**
     * @return array<string, Channel>
     */
    #[\Override]
    protected function sceneMap(): array
    {
        return [
            'work'  => Channel::WechatWork,
            'suite' => Channel::WechatWork,
        ];
    }

    #[\Override]
    protected function defaultChannel(): Channel
    {
        return Channel::WechatWork;
    }

    #[\Override]
    protected function defaultPayChannel(): Channel
    {
        return Channel::WechatWork;
    }
}
