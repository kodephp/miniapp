<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信生态统一聚合类
 *
 * 覆盖微信全场景：公众号 / 小程序 / H5 / PC / App / 开放平台 / 企业微信 / QQ
 * 一行代码搞定所有微信场景的登录 / 支付 / 回调 / 资料
 *
 * 用法：
 *   $user = Union::wechat()->mini('code');          // 小程序登录
 *   $user = Union::wechat()->mp('code');            // 公众号 OAuth
 *   $user = Union::wechat()->h5('code');            // H5 登录
 *   $user = Union::wechat()->pc('code');            // PC 扫码
 *   $user = Union::wechat()->app('code');           // 移动 App
 *   $user = Union::wechat()->open('auth_code');     // 开放平台
 *
 *   $order = Union::wechat()->pay()->createOrder([...]);
 *   $data  = Union::wechat()->notify()->decode($payload, $headers);
 *   $user  = Union::wechat()->user('openid')->profile();
 */
final class WechatUnion extends PlatformUnion
{
    #[\Override]
    public function platform(): string
    {
        return 'wechat';
    }

    // ===== 场景登录快捷方法 =====

    /**
     * 微信小程序登录
     */
    public function mini(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mini');
    }

    /**
     * 微信公众号 OAuth 登录
     */
    public function mp(string $code): UnionUser
    {
        return $this->loginByCode($code, 'mp');
    }

    /**
     * 微信 H5 登录
     */
    public function h5(string $code): UnionUser
    {
        return $this->loginByCode($code, 'h5');
    }

    /**
     * 微信 PC 网站应用扫码登录
     */
    public function pc(string $code): UnionUser
    {
        return $this->loginByCode($code, 'pc');
    }

    /**
     * 微信移动 App 登录
     */
    public function app(string $code): UnionUser
    {
        return $this->loginByCode($code, 'app');
    }

    /**
     * 微信开放平台（第三方平台代公众号 / 小程序）
     *
     * @param array<string, mixed> $payload 包含 authorization_code、authorizer_appid 等
     */
    public function open(array $payload): UnionUser
    {
        return $this->login($payload, 'open');
    }

    // ===== 场景映射 =====

    /**
     * @return array<string, Channel>
     */
    #[\Override]
    protected function sceneMap(): array
    {
        return [
            'mini' => Channel::WechatMini,
            'mp'   => Channel::WechatMp,
            'h5'   => Channel::WechatH5,
            'pc'   => Channel::WechatPc,
            'app'  => Channel::WechatApp,
            'open' => Channel::WechatOpen,
        ];
    }

    #[\Override]
    protected function defaultChannel(): Channel
    {
        return Channel::WechatMini;
    }

    #[\Override]
    protected function defaultPayChannel(): Channel
    {
        return Channel::WechatMini;
    }
}
