<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信小程序码模块
 */
readonly class MiniProgramCode
{
    private const string BASE_URL = 'https://api.weixin.qq.com/wxa';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 获取小程序码（永久有效，数量限制 10 万个）
     *
     * @param array<string, mixed> $params
     * @return string 返回图片二进制内容
     */
    public function getUnlimited(array $params): string
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/getwxacodeunlimit?access_token={$token}",
            array_merge([
                'scene' => '',
            ], $params)
        );

        $content = (string) $response->getBody();

        // 如果返回的是 JSON，说明出错了
        if (str_starts_with($content, '{')) {
            $data = json_decode($content, true);
            $errcode = $data['errcode'] ?? 'unknown';
            $errmsg  = $data['errmsg'] ?? '未知错误';
            throw new \RuntimeException("获取小程序码失败: [{$errcode}] {$errmsg}");
        }

        return $content;
    }

    /**
     * 获取小程序二维码（永久有效，数量限制 10 万个）
     *
     * @param array<string, mixed> $params
     * @return string 返回图片二进制内容
     */
    public function createQRCode(array $params): string
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/createwxaqrcode?access_token={$token}",
            array_merge([
                'path' => '',
            ], $params)
        );

        $content = (string) $response->getBody();

        if (str_starts_with($content, '{')) {
            $data = json_decode($content, true);
            $errcode = $data['errcode'] ?? 'unknown';
            $errmsg  = $data['errmsg'] ?? '未知错误';
            throw new \RuntimeException("获取小程序二维码失败: [{$errcode}] {$errmsg}");
        }

        return $content;
    }

    /**
     * 获取小程序码（临时，通过接口传入 scene）
     *
     * @param array<string, mixed> $params
     * @return string 返回图片二进制内容
     */
    public function get(array $params): string
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/getwxacode?access_token={$token}",
            array_merge([
                'path' => '',
            ], $params)
        );

        $content = (string) $response->getBody();

        if (str_starts_with($content, '{')) {
            $data = json_decode($content, true);
            $errcode = $data['errcode'] ?? 'unknown';
            $errmsg  = $data['errmsg'] ?? '未知错误';
            throw new \RuntimeException("获取小程序码失败: [{$errcode}] {$errmsg}");
        }

        return $content;
    }
}
