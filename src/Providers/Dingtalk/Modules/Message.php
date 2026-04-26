<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉消息模块
 */
readonly class Message
{
    private const string BASE_URL = 'https://oapi.dingtalk.com';

    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 发送工作通知
     *
     * @param array<string, mixed> $msg
     * @return array<string, mixed>
     */
    public function send(array $msg): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/topapi/message/corpconversation/asyncsend_v2?access_token={$token}",
            array_merge(['agent_id' => $this->app->config()->get('agent_id')], $msg)
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 发送文本消息（快捷方法）
     *
     * @param string|array<string> $userIds
     * @return array<string, mixed>
     */
    public function text(string $content, string|array $userIds): array
    {
        $users = is_array($userIds) ? implode(',', $userIds) : $userIds;

        return $this->send([
            'userid_list' => $users,
            'msg'         => [
                'msgtype' => 'text',
                'text'    => ['content' => $content],
            ],
        ]);
    }

    /**
     * 发送 Markdown 消息
     *
     * @param string|array<string> $userIds
     * @return array<string, mixed>
     */
    public function markdown(string $title, string $text, string|array $userIds): array
    {
        $users = is_array($userIds) ? implode(',', $userIds) : $userIds;

        return $this->send([
            'userid_list' => $users,
            'msg'         => [
                'msgtype'  => 'markdown',
                'markdown' => ['title' => $title, 'text' => $text],
            ],
        ]);
    }
}
