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
