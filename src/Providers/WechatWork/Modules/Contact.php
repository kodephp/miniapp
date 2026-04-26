<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信通讯录模块
 */
readonly class Contact
{
    private const string BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 创建成员
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createUser(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/user/create?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 更新成员
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateUser(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/user/update?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除成员
     *
     * @return array<string, mixed>
     */
    public function deleteUser(string $userId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/user/delete?access_token={$token}&userid={$userId}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取部门列表
     *
     * @return array<string, mixed>
     */
    public function departments(?int $id = null): array
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
     * 获取部门成员
     *
     * @return array<string, mixed>
     */
    public function departmentUsers(int $departmentId, bool $fetchChild = false): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL
            . "/user/simplelist?access_token={$token}&department_id={$departmentId}&fetch_child="
            . ($fetchChild ? 1 : 0)
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['userlist'] ?? [];
    }
}
