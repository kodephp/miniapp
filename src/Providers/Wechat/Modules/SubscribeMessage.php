<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信订阅消息模块（小程序）
 */
readonly class SubscribeMessage
{
    private const BASE_URL = 'https://api.weixin.qq.com/cgi-bin/message/subscribe';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 发送订阅消息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function send(string $openid, string $templateId, array $data, string $page = ''): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/send?access_token={$token}",
            [
                'touser'      => $openid,
                'template_id' => $templateId,
                'page'        => $page,
                'data'        => $data,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取当前帐号下的个人模板列表
     *
     * @return array<string, mixed>
     */
    public function getTemplateList(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            "https://api.weixin.qq.com/wxaapi/newtmpl/gettemplate?access_token={$token}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除帐号下的个人模板
     *
     * @return array<string, mixed>
     */
    public function deleteTemplate(string $priTmplId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            "https://api.weixin.qq.com/wxaapi/newtmpl/deltemplate?access_token={$token}",
            ['priTmplId' => $priTmplId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取小程序账号的类目
     *
     * @return array<string, mixed>
     */
    public function getCategory(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            "https://api.weixin.qq.com/wxaapi/newtmpl/getcategory?access_token={$token}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取模板标题下的关键词列表
     *
     * @return array<string, mixed>
     */
    public function getPubTemplateKeyWords(string $tid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            "https://api.weixin.qq.com/wxaapi/newtmpl/getpubtemplatekeywords?access_token={$token}&tid={$tid}"
        );

        return json_decode((string) $response->getBody(), true);
    }
}
