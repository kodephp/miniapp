<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信标签管理模块
 */
readonly class Tag
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 创建标签
     *
     * @return array<string, mixed>
     */
    public function create(string $tagName, ?int $tagId = null): array
    {
        $token    = $this->app->auth()->token();
        $data     = ['tagname' => $tagName];
        if ($tagId !== null) {
            $data['tagid'] = $tagId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/tag/create?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 更新标签
     *
     * @return array<string, mixed>
     */
    public function update(int $tagId, string $tagName): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/tag/update?access_token={$token}",
            ['tagid' => $tagId, 'tagname' => $tagName]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除标签
     *
     * @return array<string, mixed>
     */
    public function delete(int $tagId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/tag/delete?access_token={$token}&tagid={$tagId}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取标签成员
     *
     * @return array<string, mixed>
     */
    public function get(int $tagId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/tag/get?access_token={$token}&tagid={$tagId}"
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取标签成员失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 增加标签成员
     *
     * @param array<int, string> $userList
     * @return array<string, mixed>
     */
    public function addUsers(int $tagId, array $userList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/tag/addtagusers?access_token={$token}",
            ['tagid' => $tagId, 'userlist' => $userList]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除标签成员
     *
     * @param array<int, string> $userList
     * @return array<string, mixed>
     */
    public function delUsers(int $tagId, array $userList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/tag/deltagusers?access_token={$token}",
            ['tagid' => $tagId, 'userlist' => $userList]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取标签列表
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/tag/list?access_token={$token}"
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['taglist'] ?? [];
    }
}
