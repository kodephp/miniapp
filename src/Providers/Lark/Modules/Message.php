<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书消息模块
 */
readonly class Message
{
    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 发送消息
     *
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public function send(string $receiveId, string $receiveIdType, array $content): array
    {
        $token    = $this->app->auth()->token();
        $baseUrl  = $this->app->config()->get('base_url');
        $response = $this->app->http()->postJson(
            "{$baseUrl}/open-apis/im/v1/messages?receive_id_type={$receiveIdType}",
            [
                'receive_id' => $receiveId,
                'msg_type'   => $content['msg_type'],
                'content'    => json_encode($content['content']),
            ],
            ['Authorization' => "Bearer {$token}"]
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['data'] ?? [];
    }

    /**
     * 发送文本消息（快捷方法）
     *
     * @return array<string, mixed>
     */
    public function text(string $receiveId, string $text, string $receiveIdType = 'open_id'): array
    {
        return $this->send($receiveId, $receiveIdType, [
            'msg_type' => 'text',
            'content'  => ['text' => $text],
        ]);
    }

    /**
     * 发送富文本消息
     *
     * @return array<string, mixed>
     */
    public function post(string $receiveId, array $content, string $receiveIdType = 'open_id'): array
    {
        return $this->send($receiveId, $receiveIdType, [
            'msg_type' => 'post',
            'content'  => ['post' => $content],
        ]);
    }

    /**
     * 发送图片消息
     *
     * @return array<string, mixed>
     */
    public function image(string $receiveId, string $imageKey, string $receiveIdType = 'open_id'): array
    {
        return $this->send($receiveId, $receiveIdType, [
            'msg_type' => 'image',
            'content'  => ['image_key' => $imageKey],
        ]);
    }

    /**
     * 发送文件消息
     *
     * @return array<string, mixed>
     */
    public function file(string $receiveId, string $fileKey, string $receiveIdType = 'open_id'): array
    {
        return $this->send($receiveId, $receiveIdType, [
            'msg_type' => 'file',
            'content'  => ['file_key' => $fileKey],
        ]);
    }

    /**
     * 发送卡片消息
     *
     * @return array<string, mixed>
     */
    public function interactive(string $receiveId, array $card, string $receiveIdType = 'open_id'): array
    {
        return $this->send($receiveId, $receiveIdType, [
            'msg_type' => 'interactive',
            'content'  => $card,
        ]);
    }

    /**
     * 发送消息卡片（JSON 字符串）
     *
     * @return array<string, mixed>
     */
    public function sendCard(string $receiveId, string $receiveIdType, string $msgType, array $content): array
    {
        return $this->send($receiveId, $receiveIdType, [
            'msg_type' => $msgType,
            'content'  => $content,
        ]);
    }
}
