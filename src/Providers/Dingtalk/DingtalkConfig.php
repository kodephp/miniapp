<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * 钉钉配置
 */
readonly class DingtalkConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::Dingtalk, $data);
    }

    /**
     * 获取 AppKey
     */
    public function appKey(): string
    {
        return (string) ($this->all()['app_key'] ?? '');
    }

    /**
     * 获取 AppSecret
     */
    public function appSecret(): string
    {
        return (string) ($this->all()['app_secret'] ?? '');
    }

    /**
     * 获取 AgentId
     */
    public function agentId(): string
    {
        return (string) ($this->all()['agent_id'] ?? '');
    }
}
