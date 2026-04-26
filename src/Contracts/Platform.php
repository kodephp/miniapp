<?php

declare(strict_types=1);

namespace Kode\MiniApp\Contracts;

/**
 * 支持的平台枚举
 */
enum Platform: string
{
    case Wechat     = 'wechat';
    case Alipay     = 'alipay';
    case Douyin     = 'douyin';
    case Baidu      = 'baidu';
    case Qq         = 'qq';
    case WechatWork = 'wechat_work';
    case Dingtalk   = 'dingtalk';
    case Lark       = 'lark';

    /**
     * 获取平台中文名称
     */
    public function label(): string
    {
        return match ($this) {
            self::Wechat     => '微信',
            self::Alipay     => '支付宝',
            self::Douyin     => '抖音',
            self::Baidu      => '百度',
            self::Qq         => 'QQ',
            self::WechatWork => '微信企业号',
            self::Dingtalk   => '钉钉',
            self::Lark       => '飞书',
        };
    }
}
