<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信数据分析模块
 */
readonly class DataAnalysis
{
    private const string BASE_URL = 'https://api.weixin.qq.com/datacube';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 获取用户访问小程序日留存
     *
     * @return array<string, mixed>
     */
    public function getDailyRetain(string $beginDate, string $endDate): array
    {
        return $this->request('/getweanalysisappiddailyretaininfo', [
            'begin_date' => $beginDate,
            'end_date'   => $endDate,
        ]);
    }

    /**
     * 获取用户访问小程序数据概况
     *
     * @return array<string, mixed>
     */
    public function getDailySummary(string $beginDate, string $endDate): array
    {
        return $this->request('/getweanalysisappiddailysummarytrend', [
            'begin_date' => $beginDate,
            'end_date'   => $endDate,
        ]);
    }

    /**
     * 获取用户访问小程序数据日趋势
     *
     * @return array<string, mixed>
     */
    public function getDailyVisitTrend(string $beginDate, string $endDate): array
    {
        return $this->request('/getweanalysisappiddailyvisittrend', [
            'begin_date' => $beginDate,
            'end_date'   => $endDate,
        ]);
    }

    /**
     * 获取小程序新增或活跃用户的画像分布数据
     *
     * @return array<string, mixed>
     */
    public function getUserPortrait(string $beginDate, string $endDate): array
    {
        return $this->request('/getweanalysisappiduserportrait', [
            'begin_date' => $beginDate,
            'end_date'   => $endDate,
        ]);
    }

    /**
     * 获取用户小程序访问分布数据
     *
     * @return array<string, mixed>
     */
    public function getVisitDistribution(string $beginDate, string $endDate): array
    {
        return $this->request('/getweanalysisappidvisitdistribution', [
            'begin_date' => $beginDate,
            'end_date'   => $endDate,
        ]);
    }

    /**
     * 获取用户访问小程序周留存
     *
     * @return array<string, mixed>
     */
    public function getWeeklyRetain(string $beginDate, string $endDate): array
    {
        return $this->request('/getweanalysisappidweeklyretaininfo', [
            'begin_date' => $beginDate,
            'end_date'   => $endDate,
        ]);
    }

    /**
     * 获取用户访问小程序月留存
     *
     * @return array<string, mixed>
     */
    public function getMonthlyRetain(string $beginDate, string $endDate): array
    {
        return $this->request('/getweanalysisappidmonthlyretaininfo', [
            'begin_date' => $beginDate,
            'end_date'   => $endDate,
        ]);
    }

    /**
     * 发送请求
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function request(string $uri, array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "{$uri}?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }
}
