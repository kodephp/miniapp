<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Douyin;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * 抖音配置
 */
readonly class DouyinConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::Douyin, $data);
    }

    /**
     * 获取盐值（用于签名）
     */
    public function salt(): string
    {
        return (string) ($this->all()['salt'] ?? '');
    }

    /**
     * 获取应用私钥（对应开放平台「开发配置-应用公钥」处录入的公钥）
     *
     * 用于解密 getPhoneNumber 组件 code 换取的手机号等 RSA 密文数据。
     * 支持完整 PEM、纯 Base64、PKCS#1 与 PKCS#8 写法。
     */
    public function appPrivateKey(): string
    {
        return (string) ($this->all()['app_private_key'] ?? '');
    }

    /**
     * 获取支付商户号
     */
    public function mchId(): string
    {
        return (string) ($this->all()['mch_id'] ?? '');
    }

    /**
     * 获取支付 Token
     */
    public function payToken(): string
    {
        return (string) ($this->all()['pay_token'] ?? '');
    }
}
