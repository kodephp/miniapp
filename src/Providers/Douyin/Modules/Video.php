<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Douyin\Modules;

use Kode\MiniApp\Providers\Douyin\DouyinApp;

/**
 * 抖音视频模块
 */
readonly class Video
{
    private const BASE_URL = 'https://open.douyin.com';

    public function __construct(
        private DouyinApp $app,
    ) {
    }

    /**
     * 上传视频
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upload(string $accessToken, array $data): array
    {
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/video/upload/?access_token={$accessToken}&open_id={$data['open_id']}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['data']['error_code']) && $result['data']['error_code'] !== 0) {
            throw new \RuntimeException("上传视频失败: [{$result['data']['error_code']}] {$result['data']['description']}");
        }

        return $result;
    }

    /**
     * 创建视频
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(string $accessToken, array $data): array
    {
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/video/create/?access_token={$accessToken}&open_id={$data['open_id']}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['data']['error_code']) && $result['data']['error_code'] !== 0) {
            throw new \RuntimeException("创建视频失败: [{$result['data']['error_code']}] {$result['data']['description']}");
        }

        return $result;
    }

    /**
     * 查询视频列表
     *
     * @return array<string, mixed>
     */
    public function list(string $accessToken, string $openId, int $cursor = 0, int $count = 10): array
    {
        $response = $this->app->http()->get(
            self::BASE_URL . "/video/list/?access_token={$accessToken}&open_id={$openId}&cursor={$cursor}&count={$count}"
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['data']['error_code']) && $result['data']['error_code'] !== 0) {
            throw new \RuntimeException("查询视频列表失败: [{$result['data']['error_code']}] {$result['data']['description']}");
        }

        return $result;
    }

    /**
     * 查询视频数据
     *
     * @param array<int, string> $itemIds
     * @return array<string, mixed>
     */
    public function data(string $accessToken, string $openId, array $itemIds): array
    {
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/video/data/?access_token={$accessToken}&open_id={$openId}",
            ['item_ids' => $itemIds]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['data']['error_code']) && $result['data']['error_code'] !== 0) {
            throw new \RuntimeException("查询视频数据失败: [{$result['data']['error_code']}] {$result['data']['description']}");
        }

        return $result;
    }

    /**
     * 查询视频评论列表
     *
     * @return array<string, mixed>
     */
    public function commentList(string $accessToken, string $openId, string $itemId, int $cursor = 0, int $count = 10): array
    {
        $response = $this->app->http()->get(
            self::BASE_URL . "/item/comment/list/?access_token={$accessToken}&open_id={$openId}&item_id={$itemId}&cursor={$cursor}&count={$count}"
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['data']['error_code']) && $result['data']['error_code'] !== 0) {
            throw new \RuntimeException("查询评论失败: [{$result['data']['error_code']}] {$result['data']['description']}");
        }

        return $result;
    }

    /**
     * 回复视频评论
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function commentReply(string $accessToken, array $data): array
    {
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/item/comment/reply/?access_token={$accessToken}&open_id={$data['open_id']}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['data']['error_code']) && $result['data']['error_code'] !== 0) {
            throw new \RuntimeException("回复评论失败: [{$result['data']['error_code']}] {$result['data']['description']}");
        }

        return $result;
    }
}
