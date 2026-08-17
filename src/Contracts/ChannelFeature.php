<?php

declare(strict_types=1);

namespace Kode\MiniApp\Contracts;

/**
 * 渠道能力标识
 *
 * 用于「能力发现」：每个 Channel 声明自己支持哪些能力，
 * 开发者可在运行前查询（Union::capabilities），无需翻文档或撞运行时异常。
 */
enum ChannelFeature: string
{
    /** 登录 / 授权（code2session、OAuth 等） */
    case Login = 'login';

    /** 支付（统一下单 / 查询 / 退款 / 关单） */
    case Pay = 'pay';

    /** 回调通知（支付结果 / 消息推送归一化） */
    case Notify = 'notify';

    /** 用户资料拉取 */
    case User = 'user';

    /** 加密数据解密（手机号 / 用户信息 encryptedData） */
    case Decrypt = 'decrypt';

    /** 手机号获取（code 换号 / 密文解密） */
    case Phone = 'phone';

    /**
     * 能力中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::Login   => '登录',
            self::Pay     => '支付',
            self::Notify  => '回调',
            self::User    => '用户资料',
            self::Decrypt => '解密',
            self::Phone   => '手机号',
        };
    }
}
