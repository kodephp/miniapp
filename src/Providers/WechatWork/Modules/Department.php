<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信部门管理模块
 */
readonly class Department
{
    private const string BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 创建部门
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/department/create?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 更新部门
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/department/update?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除部门
     *
     * @return array<string, mixed>
     */
    public function delete(int $id): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/department/delete?access_token={$token}&id={$id}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取部门列表
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(?int $id = null): array
    {
        $token    = $this->app->auth()->token();
        $url      = self::BASE_URL . "/department/list?access_token={$token}";
        if ($id !== null) {
            $url .= "&id={$id}";
        }
        $response = $this->app->http()->get($url);
        $data     = json_decode((string) $response->getBody(), true);

        return $data['department'] ?? [];
    }

    /**
     * 获取单个部门详情
     *
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/department/get?access_token={$token}&id={$id}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取子部门ID列表
     *
     * @return array<int, int>
     */
    public function simpleList(?int $id = null): array
    {
        $token    = $this->app->auth()->token();
        $url      = self::BASE_URL . "/department/simplelist?access_token={$token}";
        if ($id !== null) {
            $url .= "&id={$id}";
        }
        $response = $this->app->http()->get($url);
        $data     = json_decode((string) $response->getBody(), true);

        return $data['department_id'] ?? [];
    }
}
