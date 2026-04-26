<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Baidu\Modules;

use Kode\MiniApp\Providers\Baidu\BaiduApp;

/**
 * 百度模板消息模块
 */
readonly class Message
{
    private const string BASE_URL = 'https://openapi.baidu.com/rest/2.0/smartapp';

    public function __construct(
        private BaiduApp $app,
    ) {
    }

    /**
     * 发送模板消息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function send(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/template/send?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errno']) && $result['errno'] !== 0) {
            throw new \RuntimeException("发送模板消息失败: [{$result['errno']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取模板列表
     *
     * @return array<string, mixed>
     */
    public function templateList(int $offset = 0, int $count = 20): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/template/librarylist?access_token={$token}&offset={$offset}&count={$count}"
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errno']) && $result['errno'] !== 0) {
            throw new \RuntimeException("获取模板列表失败: [{$result['errno']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取模板详情
     *
     * @return array<string, mixed>
     */
    public function templateDetail(string $id): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/template/libraryget?access_token={$token}&id={$id}"
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errno']) && $result['errno'] !== 0) {
            throw new \RuntimeException("获取模板详情失败: [{$result['errno']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 删除模板
     *
     * @return array<string, mixed>
     */
    public function deleteTemplate(string $templateId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/template/del?access_token={$token}",
            ['template_id' => $templateId]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errno']) && $result['errno'] !== 0) {
            throw new \RuntimeException("删除模板失败: [{$result['errno']}] {$result['errmsg']}");
        }

        return $result;
    }
}
