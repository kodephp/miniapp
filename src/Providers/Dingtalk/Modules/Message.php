<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉消息模块
 */
readonly class Message
{
    private const BASE_URL = 'https://oapi.dingtalk.com';

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

    /**
     * 发送图片消息
     *
     * @param string|array<string> $userIds
     * @return array<string, mixed>
     */
    public function image(string $mediaId, string|array $userIds): array
    {
        $users = is_array($userIds) ? implode(',', $userIds) : $userIds;

        return $this->send([
            'userid_list' => $users,
            'msg'         => [
                'msgtype' => 'image',
                'image'   => ['media_id' => $mediaId],
            ],
        ]);
    }

    /**
     * 发送文件消息
     *
     * @param string|array<string> $userIds
     * @return array<string, mixed>
     */
    public function file(string $mediaId, string|array $userIds): array
    {
        $users = is_array($userIds) ? implode(',', $userIds) : $userIds;

        return $this->send([
            'userid_list' => $users,
            'msg'         => [
                'msgtype' => 'file',
                'file'    => ['media_id' => $mediaId],
            ],
        ]);
    }

    /**
     * 发送链接消息
     *
     * @param string|array<string> $userIds
     * @return array<string, mixed>
     */
    public function link(string $title, string $text, string $messageUrl, string $picUrl, string|array $userIds): array
    {
        $users = is_array($userIds) ? implode(',', $userIds) : $userIds;

        return $this->send([
            'userid_list' => $users,
            'msg'         => [
                'msgtype' => 'link',
                'link'    => [
                    'title'      => $title,
                    'text'       => $text,
                    'messageUrl' => $messageUrl,
                    'picUrl'     => $picUrl,
                ],
            ],
        ]);
    }

    /**
     * 发送 OA 卡片消息
     *
     * @param array<string, mixed> $oa
     * @param string|array<string> $userIds
     * @return array<string, mixed>
     */
    public function oa(array $oa, string|array $userIds): array
    {
        $users = is_array($userIds) ? implode(',', $userIds) : $userIds;

        return $this->send([
            'userid_list' => $users,
            'msg'         => [
                'msgtype' => 'oa',
                'oa'      => $oa,
            ],
        ]);
    }

    /**
     * 发送 ActionCard 消息
     *
     * @param array<string, mixed> $card
     * @param string|array<string> $userIds
     * @return array<string, mixed>
     */
    public function actionCard(array $card, string|array $userIds): array
    {
        $users = is_array($userIds) ? implode(',', $userIds) : $userIds;

        return $this->send([
            'userid_list' => $users,
            'msg'         => [
                'msgtype'    => 'action_card',
                'action_card' => $card,
            ],
        ]);
    }
}
