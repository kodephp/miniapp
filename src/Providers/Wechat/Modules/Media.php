<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信素材管理模块
 */
readonly class Media
{
    private const BASE_URL = 'https://api.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 上传临时素材
     *
     * @return array<string, mixed>
     */
    public function upload(string $type, string $filePath): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->upload(
            self::BASE_URL . "/media/upload?access_token={$token}&type={$type}",
            'media',
            $filePath
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取临时素材
     */
    public function get(string $mediaId): string
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/media/get?access_token={$token}&media_id={$mediaId}"
        );

        return (string) $response->getBody();
    }

    /**
     * 上传永久素材
     *
     * @param array<int, array<string, mixed>> $articles
     * @return array<string, mixed>
     */
    public function uploadNews(array $articles): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/material/add_news?access_token={$token}",
            ['articles' => $articles]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 上传永久图片素材
     *
     * @return array<string, mixed>
     */
    public function uploadImage(string $filePath): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->upload(
            self::BASE_URL . "/material/add_material?access_token={$token}&type=image",
            'media',
            $filePath
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除永久素材
     *
     * @return array<string, mixed>
     */
    public function delete(string $mediaId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/material/del_material?access_token={$token}",
            ['media_id' => $mediaId]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
