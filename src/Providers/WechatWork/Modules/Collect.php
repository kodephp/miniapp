<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信收集表模块
 */
readonly class Collect
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin/wedoc';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 创建收集表
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public function create(array $form): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/vspform/create?access_token={$token}",
            ['form' => $form]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("创建收集表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 更新收集表
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public function update(string $formid, array $form): array
    {
        $token    = $this->app->auth()->token();
        $data     = ['formid' => $formid, 'form' => $form];
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/vspform/update?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("更新收集表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取收集表信息
     *
     * @return array<string, mixed>
     */
    public function get(string $formid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/vspform/get?access_token={$token}",
            ['formid' => $formid]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取收集表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 删除收集表
     *
     * @return array<string, mixed>
     */
    public function delete(string $formid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/vspform/delete?access_token={$token}",
            ['formid' => $formid]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("删除收集表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取收集表答案
     *
     * @return array<string, mixed>
     */
    public function getAnswer(string $formid, int $limit = 100, string $cursor = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'formid' => $formid,
            'limit'  => $limit,
        ];
        if (!empty($cursor)) {
            $data['cursor'] = $cursor;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/vspform/get_answer?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取答案失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
