<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen;

use Closure;
use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;
use Kode\MiniApp\Exceptions\ConfigException;

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

    /**
     * 平台级必填配置键（用于能力发现展示）
     *
     * 注意：实际校验走 {@see self::validate()} / {@see self::validateFeature()}，
     * 按「生效值」判断并兼容 component_appsecret / aes_key 等别名，避免用别名配置时被误判缺失。
     *
     * @return array<string>
     */
    #[\Override]
    public function requiredKeys(): array
    {
        return ['component_appid', 'component_secret', 'token', 'encoding_aes_key'];
    }

    /**
     * 特定能力的额外必填配置
     *
     *  - 登录 / 用户（第三方平台授权、代调用）需完整的第三方平台凭证
     *  - 回调（Notify）只需消息校验 token + EncodingAESKey
     *  - 支付（Pay）开放平台不支持，返回空
     *
     * @return array<string>
     */
    #[\Override]
    public function requiredKeysFor(ChannelFeature $feature): array
    {
        return match ($feature) {
            ChannelFeature::Notify           => ['token', 'encoding_aes_key'],
            ChannelFeature::Login,
            ChannelFeature::User             => $this->requiredKeys(),
            default                          => [],
        };
    }

    /**
     * 校验平台级必填配置（按生效值，兼容别名）
     */
    #[\Override]
    public function validate(): void
    {
        $this->assertEffective('component_appid', fn (): bool => $this->componentAppId() !== '', '第三方平台 AppID');
        $this->assertEffective('component_secret', fn (): bool => $this->componentSecret() !== '', '第三方平台 AppSecret');
        $this->assertEffective('token', fn (): bool => $this->token() !== '', '消息校验 Token');
        $this->assertEffective('encoding_aes_key', fn (): bool => $this->aesKey() !== '', 'EncodingAESKey');
    }

    /**
     * 校验特定能力所需的必填配置（按生效值，兼容别名）
     */
    #[\Override]
    public function validateFeature(ChannelFeature $feature): void
    {
        if ($feature === ChannelFeature::Notify) {
            $this->assertEffective('token', fn (): bool => $this->token() !== '', '消息校验 Token');
            $this->assertEffective('encoding_aes_key', fn (): bool => $this->aesKey() !== '', 'EncodingAESKey');

            return;
        }

        if ($feature === ChannelFeature::Login || $feature === ChannelFeature::User) {
            $this->validate();

            return;
        }
    }

    /**
     * 按生效值断言某个配置非空，缺失抛清晰异常
     */
    private function assertEffective(string $key, Closure $check, string $label): void
    {
        if (!$check()) {
            throw new ConfigException(sprintf(
                '[%s] 配置缺失必填项：%s（%s）',
                $this->platform()->label(),
                $label,
                $key,
            ));
        }
    }
}
