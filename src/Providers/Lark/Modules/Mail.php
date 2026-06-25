<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书邮件模块
 */
readonly class Mail
{
    private const BASE_URL = 'https://open.feishu.cn/open-apis/mail/v1';

    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 发送邮件
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function send(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . '/mailgroups/0/messages',
            $data,
            ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取邮件组列表
     *
     * @return array<string, mixed>
     */
    public function mailGroupList(int $pageSize = 50, string $pageToken = ''): array
    {
        $token = $this->app->auth()->token();
        $url   = self::BASE_URL . '/mailgroups?page_size=' . $pageSize;
        if (!empty($pageToken)) {
            $url .= '&page_token=' . $pageToken;
        }
        $response = $this->app->http()->get(
            $url,
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 创建邮件组
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createMailGroup(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->post(
            self::BASE_URL . '/mailgroups',
            [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'json'    => $data,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取邮件组详情
     *
     * @return array<string, mixed>
     */
    public function getMailGroup(string $mailGroupId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/mailgroups/{$mailGroupId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除邮件组
     *
     * @return array<string, mixed>
     */
    public function deleteMailGroup(string $mailGroupId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->delete(
            self::BASE_URL . "/mailgroups/{$mailGroupId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
