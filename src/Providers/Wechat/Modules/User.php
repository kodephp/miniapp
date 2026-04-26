<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信用户管理模块
 */
readonly class User
{
    private const string BASE_URL = 'https://api.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 获取用户列表
     *
     * @return array<string, mixed>
     */
    public function list(?string $nextOpenid = null): array
    {
        $token    = $this->app->auth()->token();
        $url      = self::BASE_URL . "/user/get?access_token={$token}";
        if ($nextOpenid) {
            $url .= "&next_openid={$nextOpenid}";
        }
        $response = $this->app->http()->get($url);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取用户信息
     *
     * @return array<string, mixed>
     */
    public function info(string $openid, string $lang = 'zh_CN'): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/user/info?access_token={$token}&openid={$openid}&lang={$lang}"
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 批量获取用户信息
     *
     * @param array<int, string> $openids
     * @return array<string, mixed>
     */
    public function batchInfo(array $openids, string $lang = 'zh_CN'): array
    {
        $token = $this->app->auth()->token();
        $list  = [];
        foreach ($openids as $openid) {
            $list[] = ['openid' => $openid, 'lang' => $lang];
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/user/info/batchget?access_token={$token}",
            ['user_list' => $list]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 更新用户备注
     *
     * @return array<string, mixed>
     */
    public function remark(string $openid, string $remark): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/user/info/updateremark?access_token={$token}",
            ['openid' => $openid, 'remark' => $remark]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取黑名单列表
     *
     * @return array<string, mixed>
     */
    public function blacklist(?string $beginOpenid = null): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/tags/members/getblacklist?access_token={$token}",
            ['begin_openid' => $beginOpenid]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 拉黑用户
     *
     * @param array<int, string> $openids
     * @return array<string, mixed>
     */
    public function batchBlacklist(array $openids): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/tags/members/batchblacklist?access_token={$token}",
            ['openid_list' => $openids]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 取消拉黑用户
     *
     * @param array<int, string> $openids
     * @return array<string, mixed>
     */
    public function batchUnblacklist(array $openids): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/tags/members/batchunblacklist?access_token={$token}",
            ['openid_list' => $openids]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
