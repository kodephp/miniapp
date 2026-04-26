<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信客服消息模块
 */
readonly class CustomerService
{
    private const string BASE_URL = 'https://api.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 发送客服消息
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    public function send(string $openid, array $message): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/message/custom/send?access_token={$token}",
            array_merge(['touser' => $openid], $message)
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 发送文本客服消息（快捷方法）
     *
     * @return array<string, mixed>
     */
    public function text(string $openid, string $content): array
    {
        return $this->send($openid, [
            'msgtype' => 'text',
            'text'    => ['content' => $content],
        ]);
    }

    /**
     * 发送图片客服消息
     *
     * @return array<string, mixed>
     */
    public function image(string $openid, string $mediaId): array
    {
        return $this->send($openid, [
            'msgtype' => 'image',
            'image'   => ['media_id' => $mediaId],
        ]);
    }

    /**
     * 发送图文客服消息
     *
     * @param array<int, array<string, string>> $articles
     * @return array<string, mixed>
     */
    public function news(string $openid, array $articles): array
    {
        return $this->send($openid, [
            'msgtype' => 'news',
            'news'    => ['articles' => $articles],
        ]);
    }

    /**
     * 获取客服列表
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/customservice/getkflist?access_token={$token}"
        );

        return json_decode((string) $response->getBody(), true);
    }
}
