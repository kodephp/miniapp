<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书日历模块
 */
readonly class Calendar
{
    private const BASE_URL = 'https://open.feishu.cn/open-apis';

    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 创建日历
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->post(
            self::BASE_URL . '/calendar/v4/calendars',
            [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'json'    => $params,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取日历列表
     *
     * @return array<string, mixed>
     */
    public function list(int $pageSize = 500, string $pageToken = ''): array
    {
        $token = $this->app->auth()->token();
        $url   = self::BASE_URL . "/calendar/v4/calendars?page_size={$pageSize}";
        if (!empty($pageToken)) {
            $url .= "&page_token={$pageToken}";
        }
        $response = $this->app->http()->get(
            $url,
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取日历详情
     *
     * @return array<string, mixed>
     */
    public function get(string $calendarId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/calendar/v4/calendars/{$calendarId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除日历
     *
     * @return array<string, mixed>
     */
    public function delete(string $calendarId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->delete(
            self::BASE_URL . "/calendar/v4/calendars/{$calendarId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 创建日程
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createEvent(string $calendarId, array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->post(
            self::BASE_URL . "/calendar/v4/calendars/{$calendarId}/events",
            [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
                'json'    => $params,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取日程列表
     *
     * @return array<string, mixed>
     */
    public function listEvents(string $calendarId, int $pageSize = 500, string $pageToken = ''): array
    {
        $token = $this->app->auth()->token();
        $url   = self::BASE_URL . "/calendar/v4/calendars/{$calendarId}/events?page_size={$pageSize}";
        if (!empty($pageToken)) {
            $url .= "&page_token={$pageToken}";
        }
        $response = $this->app->http()->get(
            $url,
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取日程详情
     *
     * @return array<string, mixed>
     */
    public function getEvent(string $calendarId, string $eventId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/calendar/v4/calendars/{$calendarId}/events/{$eventId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除日程
     *
     * @return array<string, mixed>
     */
    public function deleteEvent(string $calendarId, string $eventId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->delete(
            self::BASE_URL . "/calendar/v4/calendars/{$calendarId}/events/{$eventId}",
            ['headers' => ['Authorization' => "Bearer {$token}"]]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
