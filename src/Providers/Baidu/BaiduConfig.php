<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * 百度配置
 */
readonly class BaiduConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::Baidu, $data);
    }

    /**
     * 获取支付密钥
     */
    public function payKey(): string
    {
        return (string) ($this->all()['pay_key'] ?? '');
    }

    /**
     * 获取 DEAL_ID
     */
    public function dealId(): string
    {
        return (string) ($this->all()['deal_id'] ?? '');
    }
}
