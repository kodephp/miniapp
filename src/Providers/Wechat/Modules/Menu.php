<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信自定义菜单模块
 */
readonly class Menu
{
    private const BASE_URL = 'https://api.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 创建菜单
     *
     * @param array<string, mixed> $buttons
     * @return array<string, mixed>
     */
    public function create(array $buttons): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/menu/create?access_token={$token}",
            ['button' => $buttons]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询菜单
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/menu/get?access_token={$token}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除菜单
     *
     * @return array<string, mixed>
     */
    public function delete(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/menu/delete?access_token={$token}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 创建个性化菜单
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addConditional(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/menu/addconditional?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除个性化菜单
     *
     * @return array<string, mixed>
     */
    public function delConditional(string $menuId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/menu/delconditional?access_token={$token}",
            ['menuid' => $menuId]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
