<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Contracts;

use Kode\MiniApp\Union\Channel;

/**
 * 服务端回调（Notify）适配器接口
 *
 * 统一处理各平台支付回调、登录回调、消息回调的签名验证与解密。
 */
interface NotifyAdapter
{
    public function channel(): Channel;

    /**
     * 验证并解析回调数据
     *
     * @param array<string, mixed> $payload 平台 POST 推送的原始数据
     * @param array<string, string> $headers 平台 HTTP 头（用于签名校验）
     * @return array<string, mixed> 解析后的业务数据
     */
    public function decode(array $payload, array $headers = []): array;
}
