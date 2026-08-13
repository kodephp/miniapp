<?php

declare(strict_types=1);

namespace Kode\MiniApp\Core;

use ArrayAccess;
use JsonSerializable;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Exceptions\ApiException;
use Psr\Http\Message\ResponseInterface;

/**
 * 统一 API 响应值对象
 *
 * 各开放平台的错误字段互不相同（微信 errcode / 抖音 err_no / 百度 errno /
 * 飞书 code / 支付宝 xxx_response.code / OAuth error），本类负责归一化，
 * 让业务侧用同一套 API 判断成败与取值。
 *
 * 用法：
 *   $data = ApiResponse::fromPsr($response, Platform::Wechat)
 *       ->throwIfFailed('微信登录')
 *       ->toArray();
 *
 *   $openid = ApiResponse::fromPsr($response, Platform::Wechat)->get('data.openid');
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ApiResponse implements ArrayAccess, JsonSerializable, \Stringable
{
    /**
     * 各平台错误码字段 => 对应的错误信息字段
     *
     * @var array<string, string>
     */
    public const array ERROR_FIELDS = [
        'errcode' => 'errmsg',            // 微信 / QQ / 企业微信 / 钉钉 / 微信开放平台
        'err_no'  => 'err_tips',          // 抖音
        'errno'   => 'errmsg',            // 百度
        'code'    => 'msg',               // 飞书
        'error'   => 'error_description', // OAuth 风格（百度授权、部分网关）
    ];

    /**
     * 支付宝成功码
     */
    public const string ALIPAY_SUCCESS_CODE = '10000';

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public int $statusCode,
        public array $data,
        public string $raw,
        public ?Platform $platform = null,
    ) {
    }

    /**
     * 从 PSR-7 响应构造
     */
    public static function fromPsr(ResponseInterface $response, ?Platform $platform = null): self
    {
        $raw = (string) $response->getBody();

        return new self(
            statusCode: $response->getStatusCode(),
            data: self::decode($raw),
            raw: $raw,
            platform: $platform,
        );
    }

    /**
     * 从数组构造（便于测试与内部复用）
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?Platform $platform = null, int $statusCode = 200): self
    {
        return new self(
            statusCode: $statusCode,
            data: $data,
            raw: (string) json_encode($data, JSON_UNESCAPED_UNICODE),
            platform: $platform,
        );
    }

    /**
     * 是否调用成功
     */
    public function isSuccessful(): bool
    {
        if ($this->statusCode >= 400) {
            return false;
        }

        return $this->errorCode() === null;
    }

    /**
     * 是否调用失败
     */
    public function isFailed(): bool
    {
        return !$this->isSuccessful();
    }

    /**
     * 归一化后的错误码，成功时返回 null
     */
    public function errorCode(): int|string|null
    {
        // 支付宝：alipay_xxx_response.code
        $alipay = $this->alipayResponse();
        if ($alipay !== null) {
            $code = (string) ($alipay['code'] ?? '');
            if ($code !== '' && $code !== self::ALIPAY_SUCCESS_CODE) {
                $subCode = $alipay['sub_code'] ?? null;

                return is_string($subCode) && $subCode !== '' ? $subCode : $code;
            }

            return null;
        }

        // 支付宝错误响应挂在独立的 error_response 节点（不以 alipay_ 开头），
        // 此前未被识别，导致真实错误被当作成功（静默失败）。
        $error = $this->alipayErrorResponse();
        if ($error !== null) {
            $code    = (string) ($error['code'] ?? '');
            $subCode = $error['sub_code'] ?? null;

            return is_string($subCode) && $subCode !== '' ? $subCode : ($code !== '' ? $code : 'unknown');
        }

        foreach (array_keys(self::ERROR_FIELDS) as $field) {
            if (!$this->isErrorField($field)) {
                continue;
            }

            $value = $this->data[$field];

            // OAuth 风格：出现 error 字段即为失败
            if ($field === 'error') {
                return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
            }

            if (is_numeric($value)) {
                return (int) $value === 0 ? null : (int) $value;
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        // 抖音开放平台（open.douyin.com）错误码挂在 data 节点下，顶层不带 err_no，
        // 不识别会把失败响应判定为成功（静默失败）。
        $nested = $this->get('data.error_code');
        if (is_numeric($nested)) {
            return (int) $nested === 0 ? null : (int) $nested;
        }

        return $this->statusCode >= 400 ? $this->statusCode : null;
    }

    /**
     * 归一化后的错误信息
     */
    public function errorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        $alipay = $this->alipayResponse();
        if ($alipay !== null) {
            foreach (['sub_msg', 'msg'] as $key) {
                $msg = $alipay[$key] ?? null;
                if (is_string($msg) && $msg !== '') {
                    return $msg;
                }
            }

            return '支付宝接口返回失败';
        }

        $error = $this->alipayErrorResponse();
        if ($error !== null) {
            foreach (['sub_msg', 'msg'] as $key) {
                $msg = $error[$key] ?? null;
                if (is_string($msg) && $msg !== '') {
                    return $msg;
                }
            }

            return '支付宝接口返回失败';
        }

        foreach (self::ERROR_FIELDS as $codeField => $msgField) {
            if (!$this->isErrorField($codeField)) {
                continue;
            }
            $msg = $this->data[$msgField] ?? null;
            if (is_scalar($msg) && (string) $msg !== '') {
                return (string) $msg;
            }
        }

        // 抖音开放平台部分接口的错误信息字段为 err_msg（小程序侧为 err_tips）
        $altMsg = $this->data['err_msg'] ?? null;
        if (is_string($altMsg) && $altMsg !== '') {
            return $altMsg;
        }

        // 抖音部分接口错误信息挂在 data 节点下
        $nested = $this->get('data.description');
        if (is_string($nested) && $nested !== '') {
            return $nested;
        }

        return $this->raw !== '' ? mb_substr($this->raw, 0, 200) : "HTTP {$this->statusCode}";
    }

    /**
     * 失败时抛出 ApiException，成功时返回自身，便于链式调用
     *
     * @throws ApiException
     */
    public function throwIfFailed(?string $action = null): self
    {
        if ($this->isSuccessful()) {
            return $this;
        }

        throw new ApiException(
            message: $this->errorMessage() ?? '未知错误',
            errorCode: $this->errorCode() ?? 0,
            platform: $this->platform,
            payload: $this->data,
            action: $action,
        );
    }

    /**
     * 以点号路径读取嵌套值
     *
     * 例：$response->get('data.access_token')
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $value = $this->data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 是否存在某个点号路径
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * 读取字符串值
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * 读取整型值
     */
    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * 读取数组值
     *
     * @return array<array-key, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? $value : [];
    }

    /**
     * 提取平台的业务数据节点
     *
     * - 抖音 / 飞书：data
     * - 钉钉：result
     * - 支付宝：alipay_xxx_response
     * - 其他：整个响应体
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $alipay = $this->alipayResponse();
        if ($alipay !== null) {
            return $alipay;
        }

        foreach (['data', 'result'] as $node) {
            $value = $this->data[$node] ?? null;
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                return $value;
            }
        }

        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->raw;
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->data);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('ApiResponse 为只读对象，不支持写入');
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('ApiResponse 为只读对象，不支持删除');
    }

    /**
     * 判断某个字段在当前响应里是否真的承担「错误码」职责
     *
     * code 字段有歧义（飞书用作错误码，部分平台用作业务数据），
     * 因此仅在平台为飞书或响应同时带 msg 字段时才视为错误码。
     */
    private function isErrorField(string $field): bool
    {
        if (!array_key_exists($field, $this->data)) {
            return false;
        }

        if ($field !== 'code') {
            return true;
        }

        return $this->platform === Platform::Lark || array_key_exists('msg', $this->data);
    }

    /**
     * 支付宝网关响应节点（形如 alipay_system_oauth_token_response）
     *
     * @return array<string, mixed>|null
     */
    private function alipayResponse(): ?array
    {
        foreach ($this->data as $key => $value) {
            if (is_array($value) && str_ends_with($key, '_response') && str_starts_with($key, 'alipay_')) {
                /** @var array<string, mixed> $value */
                return $value;
            }
        }

        return null;
    }

    /**
     * 支付宝错误响应节点（形如 error_response，独立节点，不以 alipay_ 开头）
     *
     * @return array<string, mixed>|null
     */
    private function alipayErrorResponse(): ?array
    {
        $value = $this->data['error_response'] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * 安全解析 JSON（PHP 8.3 json_validate，避免非法 JSON 触发告警）
     *
     * @return array<string, mixed>
     */
    private static function decode(string $raw): array
    {
        if ($raw === '' || !json_validate($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        /** @var array<string, mixed> */
        return is_array($decoded) ? $decoded : [];
    }
}
