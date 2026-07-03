<?php

declare(strict_types=1);

namespace Kode\MiniApp\Contracts;

/**
 * Kernel 接口
 *
 * Kernel 是整个 SDK 的入口门面，聚合了所有平台 Provider，
 * 业务侧可通过 Kernel 在不同平台之间切换、桥接。
 */
interface KernelInterface
{
    /**
     * 获取指定平台的 Provider
     */
    public function get(Platform $platform): PlatformInterface;

    /**
     * 快捷获取 Provider
     */
    public function wechat(): PlatformInterface;

    /**
     * 快捷获取微信开放平台 Provider
     */
    public function wechatOpen(): PlatformInterface;

    /**
     * 快捷获取支付宝 Provider
     */
    public function alipay(): PlatformInterface;

    /**
     * 快捷获取抖音 Provider
     */
    public function douyin(): PlatformInterface;

    /**
     * 快捷获取百度 Provider
     */
    public function baidu(): PlatformInterface;

    /**
     * 快捷获取 QQ Provider
     */
    public function qq(): PlatformInterface;

    /**
     * 快捷获取企业微信 Provider
     */
    public function wechatWork(): PlatformInterface;

    /**
     * 快捷获取钉钉 Provider
     */
    public function dingtalk(): PlatformInterface;

    /**
     * 快捷获取飞书 Provider
     */
    public function lark(): PlatformInterface;
}
