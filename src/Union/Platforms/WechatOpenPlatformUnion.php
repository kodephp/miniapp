<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Platforms;

use Kode\MiniApp\Providers\WechatOpen\Events\OpenPlatformEvent;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信开放平台统一聚合类
 *
 * 微信开放平台（第三方平台）用于代公众号 / 小程序 / 移动 App 调用接口。
 * 是微信生态互联的核心：UnionID 跨应用识别同一用户。
 *
 * ⚠️ 语义说明：{@see self::login()} 走的是「第三方平台授权流程」
 * （authorization_code 换 authorizer 信息），返回的是**授权方账号**结果，
 * 不是终端用户。若要拿终端用户，请用 {@see \Kode\MiniApp\Providers\WechatOpen\Modules\Authorizer::miniProgramSession()}
 * 或对应渠道的登录适配器。
 *
 * 用法：
 *   // 第三方平台授权（authorization_code 换取 authorizer 信息）
 *   $result = Union::openPlatform()->login([
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
    #[\Override]
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
    #[\Override]
    protected function sceneMap(): array
    {
        return [
            'open' => Channel::WechatOpen,
        ];
    }

    #[\Override]
    protected function defaultChannel(): Channel
    {
        return Channel::WechatOpen;
    }

    #[\Override]
    protected function defaultPayChannel(): Channel
    {
        return Channel::WechatApp;
    }

    /**
     * 开放平台回调统一入口（解密 component_verify_ticket / 授权事件 / 授权方消息）
     *
     * ⚠️ 与基类 {@see self::notify()} 区分：基类的 notify() 返回支付回调适配器
     * （NotifyAdapter，校验支付结果签名）；本方法处理开放平台「授权事件推送」，
     * 返回结构化的 {@see OpenPlatformEvent}，两者互不干扰。
     *
     * @param array<string, mixed> $query 微信回调 URL 上的 msg_signature / timestamp / nonce
     */
    public function handleEvent(string $rawBody, array $query): OpenPlatformEvent
    {
        $app = $this->appInstance();
        if (!$app instanceof WechatOpenApp) {
            throw new \RuntimeException('开放平台回调要求 wechat_open Provider');
        }

        return $app->notify($rawBody, $query);
    }
}
