<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat;

use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * 微信配置
 */
readonly class WechatConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::Wechat, $data);
    }

    /**
     * 获取微信支付商户号
     */
    public function mchId(): string
    {
        return (string) ($this->all()['mch_id'] ?? '');
    }

    /**
     * 获取 APIv3 密钥
     */
    public function apiV3Key(): string
    {
        return (string) ($this->all()['api_v3_key'] ?? '');
    }

    /**
     * 获取证书路径
     */
    public function certPath(): ?string
    {
        return $this->all()['cert_path'] ?? null;
    }

    /**
     * 获取密钥路径
     */
    public function keyPath(): ?string
    {
        return $this->all()['key_path'] ?? null;
    }

    /**
     * 获取商户 API 证书序列号（mch_serial_no）
     *
     * 用于微信支付 V3 请求 Authorization 头的 serial_no 字段。
     */
    public function mchSerialNo(): string
    {
        return (string) ($this->all()['mch_serial_no'] ?? '');
    }

    /**
     * 服务商商户号（sp_mchid）
     *
     * 仅在「服务商模式」下配置：以服务商身份代特约商户（sub_mchid）收款。
     */
    public function spMchId(): string
    {
        return (string) ($this->all()['sp_mchid'] ?? '');
    }

    /**
     * 特约商户号（sub_mchid）
     *
     * 服务商模式下的实际收款商户。与 sp_mchid 配套使用。
     */
    public function subMchId(): string
    {
        return (string) ($this->all()['sub_mchid'] ?? '');
    }

    /**
     * 特约商户 AppID（sub_appid）
     *
     * 服务商模式下下单使用的 AppID（关联的公众号 / 小程序 / 移动应用 AppID）。
     * 缺省时回退到 app_id。
     */
    public function subAppId(): string
    {
        return (string) ($this->all()['sub_appid'] ?? '');
    }

    /**
     * 是否服务商模式（代特约商户收款）
     *
     * 配置了 sp_mchid 与 sub_mchid 即视为服务商模式，
     * 下单 / 查询 / 关单 / V3 签名均切换到服务商字段。
     */
    public function isServiceProvider(): bool
    {
        return $this->spMchId() !== '' && $this->subMchId() !== '';
    }

    /**
     * 平台级必填配置（任一微信能力都需提供 app_id）
     *
     * @return array<string>
     */
    #[\Override]
    public function requiredKeys(): array
    {
        return ['app_id'];
    }

    /**
     * 特定能力的额外必填配置
     *
     * 支付（V3）需要商户私钥、证书序列号，以及商户号：
     *  - 直连商户：mch_id；
     *  - 服务商模式：sp_mchid + sub_mchid（以服务商身份代特约商户收款）。
     * 其余能力暂无额外要求。
     *
     * @return array<string>
     */
    #[\Override]
    public function requiredKeysFor(ChannelFeature $feature): array
    {
        if ($feature !== ChannelFeature::Pay) {
            return [];
        }

        if ($this->isServiceProvider()) {
            return ['sp_mchid', 'sub_mchid', 'key_path', 'mch_serial_no'];
        }

        return ['mch_id', 'key_path', 'mch_serial_no'];
    }

    /**
     * 获取 Token（用于消息校验）
     */
    public function token(): string
    {
        return (string) ($this->all()['token'] ?? '');
    }

    /**
     * 获取 EncodingAESKey
     */
    public function aesKey(): string
    {
        return (string) ($this->all()['aes_key'] ?? '');
    }
}
