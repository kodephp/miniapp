<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay;

use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * 支付宝配置
 */
readonly class AlipayConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::Alipay, $data);
    }

    /**
     * 获取支付宝公钥
     */
    public function publicKey(): string
    {
        return (string) ($this->all()['public_key'] ?? '');
    }

    /**
     * 获取应用私钥
     */
    public function privateKey(): string
    {
        return (string) ($this->all()['private_key'] ?? '');
    }

    /**
     * 获取 AES 密钥（用于解密 my.getPhoneNumber 等客户端加密数据）
     *
     * 该密钥为开放平台「应用 AES 密钥管理」中生成的 16 字节密钥，
     * 以 base64 编码形式配置（与官方 SDK 约定一致）。
     */
    public function aesKey(): string
    {
        return (string) ($this->all()['aes_key'] ?? '');
    }

    /**
     * 获取支付宝根证书
     */
    public function rootCert(): ?string
    {
        return $this->all()['root_cert'] ?? null;
    }

    /**
     * 是否沙箱环境
     */
    public function sandbox(): bool
    {
        return (bool) ($this->all()['sandbox'] ?? false);
    }

    /**
     * 获取网关地址
     */
    public function gateway(): string
    {
        return $this->sandbox()
            ? 'https://openapi.alipaydev.com/gateway.do'
            : 'https://openapi.alipay.com/gateway.do';
    }

    /**
     * 平台级必填配置（签名与验签均需应用私钥 / 支付宝公钥）
     *
     * @return array<string>
     */
    #[\Override]
    public function requiredKeys(): array
    {
        return ['app_id', 'private_key', 'public_key'];
    }

    /**
     * 特定能力的额外必填配置（支付宝支付复用基础密钥，无额外要求）
     *
     * @return array<string>
     */
    #[\Override]
    public function requiredKeysFor(ChannelFeature $feature): array
    {
        return [];
    }
}
