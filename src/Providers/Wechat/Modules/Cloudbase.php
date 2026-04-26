<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信云开发模块
 */
readonly class Cloudbase
{
    private const BASE_URL = 'https://api.weixin.qq.com/tcb';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 调用云函数
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function invokeFunction(string $name, array $data = []): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/invokefunction?access_token={$token}",
            [
                'env'  => $this->app->config()->get('cloud_env', '') ?? '',
                'name' => $name,
                'data' => $data,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("调用云函数失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 数据库查询
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function databaseQuery(array $query): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/databasequery?access_token={$token}",
            [
                'env'   => $this->app->config()->get('cloud_env', '') ?? '',
                'query' => $query,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("数据库查询失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 数据库更新
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function databaseUpdate(array $query): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/databaseupdate?access_token={$token}",
            [
                'env'   => $this->app->config()->get('cloud_env', '') ?? '',
                'query' => $query,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("数据库更新失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 数据库添加
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function databaseAdd(array $query): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/databaseadd?access_token={$token}",
            [
                'env'   => $this->app->config()->get('cloud_env', '') ?? '',
                'query' => $query,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("数据库添加失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 数据库删除
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function databaseDelete(array $query): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/databasedelete?access_token={$token}",
            [
                'env'   => $this->app->config()->get('cloud_env', '') ?? '',
                'query' => $query,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("数据库删除失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 上传文件
     *
     * @return array<string, mixed>
     */
    public function uploadFile(string $path): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/uploadfile?access_token={$token}",
            [
                'env'  => $this->app->config()->get('cloud_env', '') ?? '',
                'path' => $path,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("上传文件失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取文件下载链接
     *
     * @param array<int, string> $fileList
     * @return array<string, mixed>
     */
    public function batchDownloadFile(array $fileList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/batchdownloadfile?access_token={$token}",
            [
                'env'       => $this->app->config()->get('cloud_env', '') ?? '',
                'file_list' => $fileList,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取下载链接失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
