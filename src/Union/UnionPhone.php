<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union;

/**
 * 统一手机号数据模型
 *
 * 与 {@see UnionUser}（用户资料）对称，把各端「手机号获取」归一化结果
 * （{@see \Kode\MiniApp\Core\PhoneNormalizer} 产出的 phoneNumber / purePhoneNumber / countryCode）
 * 收敛为强类型值对象，便于业务侧以统一结构消费，无需每次手写数组取值。
 *
 * 关键字段：
 *  - phoneNumber：带区号的完整号码（如 +86 13800138000）
 *  - purePhoneNumber：去区号的纯号码（如 13800138000）
 *  - countryCode：国家/地区码（如 86）
 */
final readonly class UnionPhone
{
    /**
     * @param array<string, mixed> $raw 原始手机号数组（含 phoneNumber / purePhoneNumber / countryCode 及平台原始字段）
     */
    public function __construct(
        public string $phoneNumber,
        public string $purePhoneNumber,
        public string $countryCode,
        public array $raw = [],
    ) {
    }

    /**
     * 从归一化手机号数组构造 UnionPhone
     *
     * 由各 Union 手机号入口（phoneByCode / phoneByDecrypt / phoneByUser / phoneByResponse）
     * 在返回数组后调用，收敛为值对象。缺失字段安全兜底为空字符串，绝不抛异常。
     *
     * @param array<string, mixed> $data 经 PhoneNormalizer 归一化的手机号数组
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phoneNumber:     is_string($data['phoneNumber'] ?? null) ? $data['phoneNumber'] : '',
            purePhoneNumber: is_string($data['purePhoneNumber'] ?? null) ? $data['purePhoneNumber'] : '',
            countryCode:     is_string($data['countryCode'] ?? null) ? $data['countryCode'] : '',
            raw:             $data,
        );
    }

    /**
     * 序列化为数组（便于持久化）
     *
     * @return array{phoneNumber:string, purePhoneNumber:string, countryCode:string}
     */
    public function toArray(): array
    {
        return [
            'phoneNumber'     => $this->phoneNumber,
            'purePhoneNumber' => $this->purePhoneNumber,
            'countryCode'     => $this->countryCode,
        ];
    }
}
