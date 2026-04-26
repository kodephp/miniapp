<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉通讯录模块
 */
readonly class Contact
{
    private const BASE_URL = 'https://oapi.dingtalk.com';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 获取部门列表
     *
     * @return array<string, mixed>
     */
    public function departments(?string $parentId = null): array
    {
        $token    = $this->app->auth()->token();
        $params   = ['access_token' => $token];
        if ($parentId !== null) {
            $params['id'] = $parentId;
        }
        $response = $this->app->http()->get(self::BASE_URL . '/department/list', ['query' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        return $data['department'] ?? [];
    }

    /**
     * 获取部门用户列表
     *
     * @return array<string, mixed>
     */
    public function departmentUsers(int $departmentId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . '/user/simplelist',
            ['query' => [
                'access_token'    => $token,
                'department_id'   => $departmentId,
            ]]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['userlist'] ?? [];
    }

    /**
     * 创建用户
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createUser(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/topapi/v2/user/create?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 更新用户
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateUser(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/topapi/v2/user/update?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除用户
     *
     * @return array<string, mixed>
     */
    public function deleteUser(string $userId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/topapi/v2/user/delete?access_token={$token}",
            ['userid' => $userId]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
