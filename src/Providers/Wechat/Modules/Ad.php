<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信广告模块
 */
readonly class Ad
{
    private const string BASE_URL = 'https://api.weixin.qq.com/promoter';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 创建广告单元
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createAdUnit(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/adunit/create?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("创建广告单元失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取广告单元列表
     *
     * @return array<string, mixed>
     */
    public function adUnitList(int $page = 1, int $pageSize = 20): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/adunit/list?access_token={$token}",
            [
                'page'      => $page,
                'page_size' => $pageSize,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取广告单元列表失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取广告数据
     *
     * @return array<string, mixed>
     */
    public function getData(string $adUnitId, string $startDate, string $endDate): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/adunit/data?access_token={$token}",
            [
                'ad_unit_id' => $adUnitId,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取广告数据失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
