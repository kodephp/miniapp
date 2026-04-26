<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信内容安全模块
 * 支持文本检测、图片检测、音频检测、视频检测等
 */
readonly class Security
{
    private const BASE_URL = 'https://api.weixin.qq.com/wxa';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 文本内容安全检测
     *
     * @return array<string, mixed>
     */
    public function msgSecCheck(string $content, int $scene = 1, string $openid = '', string $title = '', string $nickname = '', string $signature = '', string $version = '2'): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/msg_sec_check?access_token={$token}",
            [
                'content'   => $content,
                'version'   => $version,
                'scene'     => $scene,
                'openid'    => $openid,
                'title'     => $title,
                'nickname'  => $nickname,
                'signature' => $signature,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 图片内容安全检测
     *
     * @return array<string, mixed>
     */
    public function imgSecCheck(string $mediaUrl, int $scene = 1, string $openid = '', string $version = '2'): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/img_sec_check?access_token={$token}",
            [
                'media_url' => $mediaUrl,
                'media_type' => 2,
                'version'   => $version,
                'scene'     => $scene,
                'openid'    => $openid,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 音频内容安全检测
     *
     * @return array<string, mixed>
     */
    public function mediaCheckAsync(string $mediaUrl, int $mediaType, int $scene = 1, string $openid = '', string $version = '2'): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/media_check_async?access_token={$token}",
            [
                'media_url'  => $mediaUrl,
                'media_type' => $mediaType,
                'version'    => $version,
                'scene'      => $scene,
                'openid'     => $openid,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
