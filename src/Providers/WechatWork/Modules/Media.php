<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信素材管理模块
 */
readonly class Media
{
    private const string BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
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
     * 上传图片（永久，仅用于图文消息）
     *
     * @return array<string, mixed>
     */
    public function uploadImg(string $filePath): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->upload(
            self::BASE_URL . "/media/uploadimg?access_token={$token}",
            'media',
            $filePath
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 上传附件（永久，用于发送消息）
     *
     * @return array<string, mixed>
     */
    public function uploadAttachment(string $mediaType, string $filePath, string $attachmentType = 'file'): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->upload(
            self::BASE_URL . "/media/upload_attachment?access_token={$token}&media_type={$mediaType}&attachment_type={$attachmentType}",
            'media',
            $filePath
        );

        return json_decode((string) $response->getBody(), true);
    }
}
