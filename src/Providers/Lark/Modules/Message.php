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
}
