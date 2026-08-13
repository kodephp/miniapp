<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Qq\Modules;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\Qq\QqApp;
use Kode\MiniApp\Utils\Xml;

/**
 * QQ 支付回调通知模块
 *
 * QQ 小程序支付回调为 XML 格式，使用与下单一致的 MD5 签名（密钥为 api_key）。
 * 验签复用 {@see Pay::sign()} 静态方法，保证与下单签名算法逐字节一致。
 */
readonly class Notify
{
    public function __construct(
        private QqApp $app,
    ) {
    }

    /**
     * 解析并验证 QQ 支付回调通知
     *
     * 业务侧将 `php://input` 的原始 XML 传入，本方法解析为数组并校验签名；
     * 签名校验失败时抛出 {@see ApiException}，成功返回归一化前的原始字段数组。
     *
     * @return array<string, mixed>
     * @throws ApiException 签名验证失败时抛出
     */
    public function decode(string $content): array
    {
        $payload = Xml::parse($content);

        if (isset($payload['sign']) && !$this->verify($payload)) {
            throw new ApiException('QQ 支付回调签名验证失败');
        }

        return $payload;
    }

    /**
     * 验证回调签名（MD5，密钥为 api_key）
     *
     * 未配置 api_key 时跳过验证（与通用 Notify 行为一致，便于本地联调）。
     *
     * @param array<string, mixed> $payload
     */
    public function verify(array $payload): bool
    {
        $sign = (string) ($payload['sign'] ?? '');
        if ($sign === '') {
            return false;
        }

        unset($payload['sign']);

        $key = $this->app->config()->get('api_key', '');
        if ($key === '') {
            return true; // 未配置密钥时跳过验证
        }

        return Pay::sign($payload, $key) === $sign;
    }
}
