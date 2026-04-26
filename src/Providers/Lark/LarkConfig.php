<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\BaseConfig;

/**
 * 飞书配置
 */
readonly class LarkConfig extends BaseConfig
{
    public function __construct(array $data)
    {
        parent::__construct(Platform::Lark, $data);
    }

    /**
     * 是否使用飞书国内版（默认 true）
     */
    public function isFeishu(): bool
    {
        return (bool) ($this->all()['is_feishu'] ?? true);
    }

    /**
     * 获取基础域名
     */
    public function baseUrl(): string
    {
        return $this->isFeishu()
            ? 'https://open.feishu.cn'
            : 'https://open.larksuite.com';
    }

    /**
     * 获取 Encrypt Key（用于事件回调解密）
     */
    public function encryptKey(): string
    {
        return (string) ($this->all()['encrypt_key'] ?? '');
    }

    /**
     * 获取 Verification Token
     */
    public function verificationToken(): string
    {
        return (string) ($this->all()['verification_token'] ?? '');
    }
}
