<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union;

/**
 * 支持的统一登录 / 鉴权渠道
 *
 * 每个渠道对应一个具体平台的具体场景，
 * 由 Union 入口根据此枚举分派到对应的 Channel 适配器。
 */
enum Channel: string
{
    // ===== 微信生态 =====
    /** 公众号（OAuth 网页授权） */
    case WechatMp        = 'wechat_mp';

    /** 小程序登录（jscode2session） */
    case WechatMini      = 'wechat_mini';

    /** H5（公众号 H5 / 微信内 H5） */
    case WechatH5        = 'wechat_h5';

    /** PC 网站应用（开放平台扫码） */
    case WechatPc        = 'wechat_pc';

    /** 移动 App（开放平台移动应用） */
    case WechatApp       = 'wechat_app';

    /** 微信开放平台（第三方平台代公众号 / 小程序） */
    case WechatOpen      = 'wechat_open';

    /** 企业微信（企业内部应用 / 第三方应用） */
    case WechatWork      = 'wechat_work';

    /** QQ（QQ 与微信账号体系互通） */
    case Qq              = 'qq';

    // ===== 阿里生态 =====
    /** 支付宝小程序 */
    case AlipayMini      = 'alipay_mini';

    /** 支付宝生活号 */
    case AlipayMp        = 'alipay_mp';

    /** 支付宝 App 支付 */
    case AlipayApp       = 'alipay_app';

    // ===== 字节生态 =====
    /** 抖音小程序 */
    case DouyinMini      = 'douyin_mini';

    /** 抖音头条号 */
    case DouyinMp        = 'douyin_mp';

    // ===== 百度 =====
    /** 百度小程序 */
    case BaiduMini       = 'baidu_mini';

    // ===== 协同办公 =====
    /** 钉钉 */
    case Dingtalk        = 'dingtalk';

    /** 飞书 */
    case Lark            = 'lark';

    /**
     * 是否属于微信生态
     */
    public function isWechatEcosystem(): bool
    {
        return match ($this) {
            self::WechatMp,
            self::WechatMini,
            self::WechatH5,
            self::WechatPc,
            self::WechatApp,
            self::WechatOpen,
            self::WechatWork,
            self::Qq => true,
            default => false,
        };
    }

    /**
     * 获取渠道的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::WechatMp   => '微信公众',
            self::WechatMini => '微信小程序',
            self::WechatH5   => '微信 H5',
            self::WechatPc   => '微信 PC',
            self::WechatApp  => '微信 APP',
            self::WechatOpen => '微信开放平台',
            self::WechatWork => '企业微信',
            self::Qq         => 'QQ',
            self::AlipayMini => '支付宝小程序',
            self::AlipayMp   => '支付宝生活号',
            self::AlipayApp  => '支付宝 APP',
            self::DouyinMini => '抖音小程序',
            self::DouyinMp   => '抖音头条号',
            self::BaiduMini  => '百度小程序',
            self::Dingtalk   => '钉钉',
            self::Lark       => '飞书',
        };
    }
}
