<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信开放平台统一聚合类
 *
 * 微信开放平台（第三方平台）用于代公众号 / 小程序 / 移动 App 调用接口。
 * 是微信生态互联的核心：UnionID 跨应用识别同一用户。
 *
 * 用法：
 *   // 第三方平台登录（authorization_code 换取 authorizer 信息）
 *   $user = Union::openPlatform()->login([
 *       'authorization_code'      => 'AUTH_CODE',
 *       'component_access_token'  => 'COMP_TOK',
 *   ]);
 *
 *   // 直接获取开放平台 App 实例做细粒度操作
 *   $app = Union::openPlatform()->appInstance();
 *   $app->component()->loginPage($preAuthCode, $callbackUrl);
 */
final class WechatOpenPlatformUnion extends PlatformUnion
{
    public function platform(): string
    {
        return 'wechat_open';
    }

    /**
     * 开放平台代登录
     *
     * @param array<string, mixed> $payload 包含 authorization_code、authorizer_appid 等
     */
    public function open(array $payload): UnionUser
    {
        return $this->login($payload, 'open');
    }

    /**
     * @return array<string, Channel>
     */
    protected function sceneMap(): array
    {
        return [
            'open' => Channel::WechatOpen,
        ];
    }

    protected function defaultChannel(): Channel
    {
        return Channel::WechatOpen;
    }

    protected function defaultPayChannel(): Channel
    {
        return Channel::WechatApp;
    }
}
