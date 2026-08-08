<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union;

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
        $avatar   = self::str($raw, ['headimgurl', 'avatarUrl', 'avatar', 'figureurl', 'avatar_url']);
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
