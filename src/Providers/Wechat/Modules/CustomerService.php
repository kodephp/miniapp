<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信客服消息模块
 */
readonly class CustomerService
{
    private const BASE_URL = 'https://api.weixin.qq.com/cgi-bin';

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
     * 发送小程序卡片客服消息
     *
     * @return array<string, mixed>
     */
    public function miniProgramPage(string $openid, string $title, string $appid, string $pagePath, string $thumbMediaId): array
    {
        return $this->send($openid, [
            'msgtype' => 'miniprogrampage',
            'miniprogrampage' => [
                'title'          => $title,
                'appid'          => $appid,
                'pagepath'       => $pagePath,
                'thumb_media_id' => $thumbMediaId,
            ],
        ]);
    }

    /**
     * 发送菜单客服消息
     *
     * @param array<int, array<string, string>> $buttons
     * @return array<string, mixed>
     */
    public function menu(string $openid, string $headContent, array $buttons, string $tailContent = ''): array
    {
        return $this->send($openid, [
            'msgtype' => 'msgmenu',
            'msgmenu' => [
                'head_content' => $headContent,
                'list'         => $buttons,
                'tail_content' => $tailContent,
            ],
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

    /**
     * 获取客服聊天记录
     *
     * @return array<string, mixed>
     */
    public function msgRecord(int $startTime, int $endTime, int $msgid = 1, int $number = 10000): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/customservice/msgrecord/getmsglist?access_token={$token}",
            [
                'starttime' => $startTime,
                'endtime'   => $endTime,
                'msgid'     => $msgid,
                'number'    => $number,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 邀请用户加入客服会话
     *
     * @return array<string, mixed>
     */
    public function invite(string $openid, string $kfAccount): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/message/custom/service/send?access_token={$token}",
            [
                'touser'    => $openid,
                'msgtype'   => 'transfer_customer_service',
                'transinfo' => ['kf_account' => $kfAccount],
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
