<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * 微信开放平台配置
 *
 * 第三方平台配置：appid、appsecret 用于获取 component_access_token；
 * 消息加密相关 token / aes_key 用于接收微信推送的 ticket、授权事件。
 */
readonly class WechatOpenConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::WechatOpen, $data);
    }

    /**
     * 第三方平台 AppID
     */
    public function componentAppId(): string
    {
        return (string) ($this->all()['component_appid'] ?? $this->appId());
    }

    /**
     * 第三方平台 AppSecret
     */
    public function componentSecret(): string
    {
        $data = $this->all();

        return (string) ($data['component_secret'] ?? $data['component_appsecret'] ?? $this->secret());
    }

    /**
     * 消息校验 Token
     */
    public function token(): string
    {
        return (string) ($this->all()['token'] ?? '');
    }

    /**
     * 消息加解密 EncodingAESKey
     */
    public function aesKey(): string
    {
        return (string) ($this->all()['encoding_aes_key'] ?? $this->all()['aes_key'] ?? '');
    }

    /**
     * 授权方信息：预授权的 appid 列表（可选）
     *
     * @return array<int, string>
     */
    public function preAuthorizeApps(): array
    {
        $value = $this->all()['pre_auth_apps'] ?? [];

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
