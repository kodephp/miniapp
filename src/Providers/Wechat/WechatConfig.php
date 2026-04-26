<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat;

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
