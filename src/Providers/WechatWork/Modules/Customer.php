<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信客户联系模块
 */
readonly class Customer
{
    private const string BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 获取客户列表
     *
     * @return array<string, mixed>
     */
    public function list(string $userid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/externalcontact/list?access_token={$token}&userid={$userid}"
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['external_userid'] ?? [];
    }

    /**
     * 获取客户详情
     *
     * @return array<string, mixed>
     */
    public function detail(string $externalUserid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/externalcontact/get?access_token={$token}&external_userid={$externalUserid}"
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取客户详情失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 获取跟进成员列表
     *
     * @return array<string, mixed>
     */
    public function followers(string $externalUserid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/externalcontact/get_follow_user_list?access_token={$token}"
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['follow_user'] ?? [];
    }

    /**
     * 配置客户联系「联系我」方式
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function addContactWay(array $config): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/add_contact_way?access_token={$token}",
            $config
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 发送欢迎语
     *
     * @param array<string, mixed> $welcome
     * @return array<string, mixed>
     */
    public function sendWelcomeMsg(array $welcome): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/send_welcome_msg?access_token={$token}",
            $welcome
        );

        return json_decode((string) $response->getBody(), true);
    }
}
