<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信微盘模块
 */
readonly class Drive
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin/wedrive';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 新建空间
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function spaceCreate(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/space_create?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("新建空间失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取空间信息
     *
     * @return array<string, mixed>
     */
    public function spaceInfo(string $spaceId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/space_info?access_token={$token}",
            ['spaceid' => $spaceId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取空间信息失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取文件列表
     *
     * @return array<string, mixed>
     */
    public function fileList(string $spaceId, string $fatherId = '', int $start = 0, int $limit = 100): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'spaceid'   => $spaceId,
            'start'     => $start,
            'limit'     => $limit,
        ];
        if (!empty($fatherId)) {
            $data['fatherid'] = $fatherId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/file_list?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取文件列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 上传文件
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function fileUpload(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/file_upload?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("上传文件失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 下载文件
     *
     * @return array<string, mixed>
     */
    public function fileDownload(string $spaceId, string $fileId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/file_download?access_token={$token}",
            [
                'spaceid' => $spaceId,
                'fileid'  => $fileId,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("下载文件失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 删除文件
     *
     * @return array<string, mixed>
     */
    public function fileDelete(string $spaceId, string $fileId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/file_delete?access_token={$token}",
            [
                'spaceid' => $spaceId,
                'fileid'  => $fileId,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("删除文件失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
