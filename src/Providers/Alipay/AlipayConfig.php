<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay;

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
}
