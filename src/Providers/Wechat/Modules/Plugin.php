<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信小程序插件管理模块
 */
readonly class Plugin
{
    private const string BASE_URL = 'https://api.weixin.qq.com/wxa';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 申请使用插件
     *
     * @return array<string, mixed>
     */
    public function applyPlugin(string $pluginAppid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/plugin?access_token={$token}",
            ['action' => 'apply', 'plugin_appid' => $pluginAppid]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询已添加的插件
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/plugin?access_token={$token}",
            ['action' => 'list']
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除已添加的插件
     *
     * @return array<string, mixed>
     */
    public function unbindPlugin(string $pluginAppid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/plugin?access_token={$token}",
            ['action' => 'unbind', 'plugin_appid' => $pluginAppid]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
