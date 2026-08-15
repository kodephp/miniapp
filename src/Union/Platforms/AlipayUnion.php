<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 支付宝统一聚合类
 *
 * 覆盖支付宝全场景：生活号 / 小程序 / App 支付
 *
 * 用法：
 *   $user = Union::alipay()->mini('code');   // 支付宝小程序
 *   $user = Union::alipay()->mp('code');     // 支付宝生活号
 *
 *   $order = Union::alipay()->pay()->createOrder([...]);
 *   $data  = Union::alipay()->notify()->decode($payload, $headers);
 */
final class AlipayUnion extends PlatformUnion
{
    #[\Override]
    public function platform(): string
    {
        return 'alipay';
    }

    /**
     * 支付宝小程序登录
     */
    public function mini(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mini');
    }

    /**
     * 支付宝生活号登录
     */
    public function mp(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mp');
    }

    /**
     * 支付宝 App 登录
     */
    #[\Override]
    public function loginByCode(string $code, ?string $scene = null): UnionUser
    {
        return parent::loginByCode($code, $scene);
    }

    /**
     * @return array<string, Channel>
     */
    #[\Override]
    protected function sceneMap(): array
    {
        return [
            'mini' => Channel::AlipayMini,
            'mp'   => Channel::AlipayMp,
            'app'  => Channel::AlipayApp,
        ];
    }

    #[\Override]
    protected function defaultChannel(): Channel
    {
        return Channel::AlipayMini;
    }

    #[\Override]
    protected function defaultPayChannel(): Channel
    {
        return Channel::AlipayMini;
    }
}
