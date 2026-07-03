<?php

declare(strict_types=1);

namespace Kode\MiniApp\Session;

/**
 * 登录约束策略
 *
 * 解决"一个用户多端登录"的业务约束问题。
 * 与 kode/jwt 的边界：kode/jwt 只负责 token 的签发/验证（stateless），
 * 而本策略由本包的 SessionManager 处理（stateful，会话存储）。
 */
enum SessionPolicy: string
{
    /**
     * 允许多端同时登录（默认行为，无约束）
     *
     * 场景：用户在小程序、APP、PC 端同时登录，不踢任何会话。
     */
    case Multi = 'multi';

    /**
     * 单端单账号：每个端口（设备）只能登录一个账号
     *
     * 场景：同一台设备已登录账号 A，再登录账号 B 时，账号 A 会被踢下线。
     * 约束维度：client + clientId
     * 适用：移动 App、共享设备场景
     */
    case SingleEnd = 'single_end';

    /**
     * 单账号单端：每个账号只能在一个端口登录
     *
     * 场景：用户已在小程序登录，再从 PC 登录时，小程序会话被踢下线。
     * 约束维度：unionId（按账号）
     * 适用：会员订阅、内容付费等高价值账号保护
     */
    case SingleUser = 'single_user';

    /**
     * 单账号全端：每个账号只能登录一次（最强约束）
     *
     * 场景：用户已在任何端登录，再从任何端登录时，所有其他端的会话被踢下线。
     * 约束维度：unionId（强制全局唯一）
     * 适用：管理后台、付费视频（优酷/腾讯视频）等场景
     */
    case SingleAll = 'single_all';

    public function label(): string
    {
        return match ($this) {
            self::Multi        => '多端可同时登录',
            self::SingleEnd    => '单端单账号（同设备只能登录一个账号）',
            self::SingleUser   => '单账号单端（同账号只能在一个端登录）',
            self::SingleAll    => '单账号全端（同账号只能登录一次）',
        };
    }
}
