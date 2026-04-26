<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信搜一搜模块
 */
readonly class Search
{
    private const BASE_URL = 'https://api.weixin.qq.com/ma/search';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 提交小程序页面 URL 供微信搜一搜收录
     *
     * @param array<int, string> $pages
     * @return array<string, mixed>
     */
    public function submitPages(array $pages): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/submit_pages?access_token={$token}",
            ['pages' => $pages]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("提交页面失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取搜一搜数据统计
     *
     * @return array<string, mixed>
     */
    public function getData(string $startDate, string $endDate): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_data?access_token={$token}",
            [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取搜一搜数据失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
