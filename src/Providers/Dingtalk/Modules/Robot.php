<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Dingtalk\Modules;

use Kode\MiniApp\Providers\Dingtalk\DingtalkApp;

/**
 * 钉钉群机器人模块
 */
readonly class Robot
{
    public function __construct(
        private DingtalkApp $app,
    ) {
    }

    /**
     * 发送群机器人消息（使用 Webhook）
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    public function send(string $webhook, string $secret, array $message): array
    {
        $timestamp = time() * 1000;
        $sign      = $this->sign($timestamp, $secret);
        $url       = "{$webhook}&timestamp={$timestamp}&sign={$sign}";

        $response = $this->app->http()->postJson($url, $message);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 发送文本消息
     *
     * @return array<string, mixed>
     */
    public function text(string $webhook, string $secret, string $content, array $at = []): array
    {
        return $this->send($webhook, $secret, [
            'msgtype' => 'text',
            'text'    => ['content' => $content],
            'at'      => $at,
        ]);
    }

    /**
     * 发送 Markdown 消息
     *
     * @return array<string, mixed>
     */
    public function markdown(string $webhook, string $secret, string $title, string $text, array $at = []): array
    {
        return $this->send($webhook, $secret, [
            'msgtype'  => 'markdown',
            'markdown' => ['title' => $title, 'text' => $text],
            'at'       => $at,
        ]);
    }

    /**
     * 发送链接消息
     *
     * @return array<string, mixed>
     */
    public function link(string $webhook, string $secret, string $title, string $text, string $messageUrl, string $picUrl = ''): array
    {
        return $this->send($webhook, $secret, [
            'msgtype' => 'link',
            'link'    => [
                'title'       => $title,
                'text'        => $text,
                'messageUrl'  => $messageUrl,
                'picUrl'      => $picUrl,
            ],
        ]);
    }

    /**
     * 发送 ActionCard 消息
     *
     * @return array<string, mixed>
     */
    public function actionCard(string $webhook, string $secret, array $card): array
    {
        return $this->send($webhook, $secret, [
            'msgtype'    => 'action_card',
            'actionCard' => $card,
        ]);
    }

    /**
     * 发送 FeedCard 消息
     *
     * @param array<int, array<string, string>> $links
     * @return array<string, mixed>
     */
    public function feedCard(string $webhook, string $secret, array $links): array
    {
        return $this->send($webhook, $secret, [
            'msgtype'  => 'feedCard',
            'feedCard' => ['links' => $links],
        ]);
    }

    /**
     * 计算签名
     */
    private function sign(int $timestamp, string $secret): string
    {
        $string = $timestamp . "\n" . $secret;
        $hash   = hash_hmac('sha256', $string, $secret, true);

        return urlencode(base64_encode($hash));
    }
}
