<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * 微信企业号配置
 */
readonly class WechatWorkConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::WechatWork, $data);
    }

    /**
     * 获取企业 ID（corp_id）
     */
    public function corpId(): string
    {
        return (string) ($this->all()['corp_id'] ?? '');
    }

    /**
     * 获取应用 AgentId
     */
    public function agentId(): string
    {
        return (string) ($this->all()['agent_id'] ?? '');
    }

    /**
     * 获取通讯录管理 Secret
     */
    public function contactSecret(): string
    {
        return (string) ($this->all()['contact_secret'] ?? '');
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
