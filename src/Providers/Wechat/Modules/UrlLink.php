<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信小程序 URL Scheme / URL Link 模块
 * 支持生成小程序短链接、URL Scheme、URL Link
 */
readonly class UrlLink
{
    private const BASE_URL = 'https://api.weixin.qq.com/wxa';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 生成 URL Scheme（适用于短信、邮件、微信外打开小程序）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function generateScheme(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/generatescheme?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 生成 URL Link（适用于短信、邮件、微信外打开小程序）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function generateUrlLink(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/generate_urllink?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 生成 Short Link（适用于微信内拉起小程序）
     *
     * @return array<string, mixed>
     */
    public function generateShortLink(string $pageUrl, string $pageTitle = '', bool $isPermanent = false): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/genwxashortlink?access_token={$token}",
            [
                'page_url'       => $pageUrl,
                'page_title'     => $pageTitle,
                'is_permanent'   => $isPermanent,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询 URL Scheme 配额
     *
     * @return array<string, mixed>
     */
    public function querySchemeQuota(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/queryschemequota?access_token={$token}",
            ['appid' => $this->app->config()->appId()]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询 URL Link 配额
     *
     * @return array<string, mixed>
     */
    public function queryUrlLinkQuota(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/queryurllinkquota?access_token={$token}",
            ['appid' => $this->app->config()->appId()]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
