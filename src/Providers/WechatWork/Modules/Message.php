<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信消息推送模块
 */
readonly class Message
{
    private const string BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 发送应用消息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function send(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/message/send?access_token={$token}",
            array_merge(['agentid' => $this->app->config()->get('agent_id')], $data)
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 发送文本消息（快捷方法）
     *
     * @param string|array<string> $toUser  用户 ID 或 ID 数组
     * @return array<string, mixed>
     */
    public function text(string $content, string|array $toUser = '@all'): array
    {
        $users = is_array($toUser) ? implode('|', $toUser) : $toUser;

        return $this->send([
            'touser'  => $users,
            'msgtype' => 'text',
            'text'    => ['content' => $content],
        ]);
    }

    /**
     * 发送 Markdown 消息
     *
     * @param string|array<string> $toUser
     * @return array<string, mixed>
     */
    public function markdown(string $content, string|array $toUser = '@all'): array
    {
        $users = is_array($toUser) ? implode('|', $toUser) : $toUser;

        return $this->send([
            'touser'   => $users,
            'msgtype'  => 'markdown',
            'markdown' => ['content' => $content],
        ]);
    }
}
