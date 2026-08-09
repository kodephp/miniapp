<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union;

use Kode\MiniApp\Core\UserInfoNormalizer;

/**
 * 统一用户数据模型
 *
 * 跨平台登录时，无论用户从公众号 / 小程序 / PC / App 哪个渠道登录，
 * 业务侧拿到的都是 UnionUser，字段含义保持一致。
 *
 * 关键字段：
 *  - unionId：跨平台统一 ID（同一开放平台下所有应用共享）
 *  - openId：平台内唯一 OpenID
 *  - channel：来源渠道（Channel 枚举）
 *  - nickname / avatar / ...：标准化的用户基本信息
 */
final readonly class UnionUser
{
    /**
     * @param array<string, mixed> $raw 平台原始响应数据
     * @param array<string, mixed> $extra 平台扩展信息（如 access_token、refresh_token、scope 等）
     */
    public function __construct(
        public string $unionId,
        public string $openId,
        public Channel $channel,
        public ?string $nickname = null,
        public ?string $avatar = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $gender = null,
        public ?string $country = null,
        public ?string $province = null,
        public ?string $city = null,
        public array $raw = [],
        public array $extra = [],
    ) {
    }

    /**
     * 是否包含 unionId（用于跨平台识别）
     */
    public function hasUnionId(): bool
    {
        return $this->unionId !== '';
    }

    /**
     * 序列化为数组（便于持久化到业务用户表）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'union_id' => $this->unionId,
            'open_id'  => $this->openId,
            'channel'  => $this->channel->value,
            'nickname' => $this->nickname,
            'avatar'   => $this->avatar,
            'email'    => $this->email,
            'phone'    => $this->phone,
            'gender'   => $this->gender,
            'country'  => $this->country,
            'province' => $this->province,
            'city'     => $this->city,
            'extra'    => $this->extra,
        ];
    }

    /**
     * 从平台原始数据构造 UnionUser
     *
     * 由各 Channel 适配器调用，提取平台特有字段到统一字段。
     *
     * @param array<string, mixed> $raw 平台原始数据
     * @param array<string, mixed> $extra 平台扩展信息
     */
    public static function fromRaw(
        Channel $channel,
        string $openId,
        string $unionId = '',
        array $raw = [],
        array $extra = [],
    ): self {
        $nickname = self::str($raw, ['nickname', 'nick_name', 'nick', 'name', 'display_name', 'user_name']);
        $avatar   = self::str($raw, [
            'headimgurl', 'avatarUrl', 'avatar', 'figureurl_qq_2', 'figureurl_qq_1', 'figureurl', 'avatar_url',
        ]);
        $email    = self::str($raw, ['email']);
        $phone    = self::str($raw, ['phone', 'mobile', 'phoneNumber']);
        $gender   = self::str($raw, ['sex', 'gender']);
        $country  = self::str($raw, ['country']);
        $province = self::str($raw, ['province']);
        $city     = self::str($raw, ['city']);

        return new self(
            unionId:  $unionId,
            openId:   $openId,
            channel:  $channel,
            nickname: $nickname,
            avatar:   $avatar,
            email:    $email,
            phone:    $phone,
            gender:   $gender,
            country:  $country,
            province: $province,
            city:     $city,
            raw:      $raw,
            extra:    $extra,
        );
    }

    /**
     * 从「客户端加密用户资料」解密结果构造 UnionUser（与 profile / 登录链路同一对象）
     *
     * 把 {@see \Kode\MiniApp\Core\UserInfoNormalizer} 归一化后的 encryptedData 用户资料
     * （兼容微信 getUserInfo 的 nickName / avatarUrl / gender / city / province / country / language）
     * 收敛到与登录 / profile 链路完全相同的 UnionUser 对象，业务侧无需再手写字段映射。
     *
     * 与 {@see fromRaw()} 的差异（关键）：本方法对 gender 仅做「透传 + 类型归一化」，
     * **不做** 0/1/2 → male/female 的枚举映射（各端 gender 编码不一致，臆测会导致错误，
     * 参见 v1.34.0 的设计取舍）。openId / unionId 在加密用户资料明文里并不存在
     * （它们来自登录 code2session），故由调用方显式传入，缺失时留空字符串。
     *
     * @param array<string, mixed> $info      userInfoByDecrypt / userInfoByUser 返回的用户资料数组
     *                                          （原始字段 + snake_case canonical 键均可）
     * @param string|null          $openId    可选，加密资料对应的用户 openId（来自登录）
     * @param string|null          $unionId   可选，跨平台 unionId（来自开放平台）
     */
    public static function fromDecryptedUserInfo(
        Channel $channel,
        array $info,
        ?string $openId = null,
        ?string $unionId = null,
    ): self {
        $data = UserInfoNormalizer::normalize($info);

        $gender = self::normalizeGender($data['gender']);

        return new self(
            unionId:  $unionId ?? '',
            openId:   $openId ?? '',
            channel:  $channel,
            nickname: self::nullIfEmpty($data['nickname']),
            avatar:   self::nullIfEmpty($data['avatar']),
            gender:   $gender,
            country:  self::nullIfEmpty($data['country']),
            province: self::nullIfEmpty($data['province']),
            city:     self::nullIfEmpty($data['city']),
            raw:      $info,
        );
    }

    /**
     * gender 透传 + 类型归一化：仅非空字符串原样保留，int / float 转字符串以契合 ?string，
     * 其余（缺字段、空串、bool、null 等）统一为 null。绝不枚举映射。
     */
    private static function normalizeGender(mixed $gender): ?string
    {
        if (is_string($gender)) {
            return $gender === '' ? null : $gender;
        }
        if (is_int($gender) || is_float($gender)) {
            return (string) $gender;
        }
        return null;
    }

    /**
     * 空字符串归一化为 null（UnionUser 字段语义：null 表示未知 / 未提供）
     */
    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>   $keys
     */
    private static function str(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                continue;
            }
            $value = $data[$key];
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value)) {
                $map = [1 => 'male', 2 => 'female', 0 => 'unknown'];
                return $map[$value] ?? (string) $value;
            }
        }

        return null;
    }
}
