<?php

declare(strict_types=1);

namespace Kode\MiniApp\Notify;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Utils\Sign;
use Kode\MiniApp\Utils\Xml;

/**
 * 支付回调通知处理器
 * 统一处理各平台的支付结果通知
 */
final class Notify
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct(
        private readonly AppInterface $app,
    ) {
    }

    /**
     * 注册支付成功处理器
     */
    public function onPaid(callable $handler): self
    {
        $this->handlers['paid'] = $handler;

        return $this;
    }

    /**
     * 注册退款通知处理器
     */
    public function onRefund(callable $handler): self
    {
        $this->handlers['refund'] = $handler;

        return $this;
    }

    /**
     * 处理回调通知
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $content = file_get_contents('php://input') ?: '';
        $payload = $this->parsePayload($content);

        // 微信/企业微信/QQ 的 XML 通知
        if (isset($payload['sign'])) {
            if (!$this->verifyWechat($payload)) {
                return ['code' => 'FAIL', 'message' => '签名验证失败'];
            }
        }

        // 支付宝通知
        if (isset($payload['sign_type'])) {
            if (!$this->verifyAlipay($payload)) {
                return ['code' => 'FAIL', 'message' => '签名验证失败'];
            }
        }

        // 触发处理器
        $event = $this->resolveEvent($payload);
        $handler = $this->handlers[$event] ?? null;

        if ($handler !== null) {
            $handler($payload, $this->app);
        }

        return ['code' => 'SUCCESS', 'message' => 'OK'];
    }

    /**
     * 解析通知内容
     *
     * @return array<string, mixed>
     */
    private function parsePayload(string $content): array
    {
        if (str_starts_with(trim($content), '<')) {
            return Xml::parse($content);
        }

        $json = json_decode($content, true);
        if (is_array($json)) {
            return $json;
        }

        // 支付宝 form 数据
        parse_str($content, $data);

        return is_array($data) ? $data : [];
    }

    /**
     * 验证微信签名
     *
     * @param array<string, mixed> $payload
     */
    private function verifyWechat(array $payload): bool
    {
        $sign = $payload['sign'] ?? '';
        unset($payload['sign']);

        $key = $this->app->config()->get('api_v3_key', '');
        if (empty($key)) {
            return true; // 未配置密钥，跳过验证
        }

        return Sign::md5($payload, $key) === $sign;
    }

    /**
     * 验证支付宝签名
     *
     * @param array<string, mixed> $payload
     */
    private function verifyAlipay(array $payload): bool
    {
        $sign     = $payload['sign'] ?? '';
        $signType = $payload['sign_type'] ?? 'RSA2';
        unset($payload['sign'], $payload['sign_type']);

        $publicKey = $this->app->config()->get('public_key', '');
        if (empty($publicKey)) {
            return true;
        }

        $algo = $signType === 'RSA2' ? 'sha256' : 'sha1';

        return Sign::verifyRsa($payload, $publicKey, $sign, $algo);
    }

    /**
     * 解析事件类型
     *
     * @param array<string, mixed> $payload
     */
    private function resolveEvent(array $payload): string
    {
        if (isset($payload['refund_id']) || isset($payload['refund_status'])) {
            return 'refund';
        }

        return 'paid';
    }
}
