<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;

/**
 * 授权方管理模块
 *
 * 用于代公众号 / 小程序调用官方接口，封装了开放平台下授权方常用的
 * 账号信息查询、用户管理、消息发送、菜单、自定义菜单、二维码、卡券等。
 */
readonly class Authorizer
{
    private const API_BASE = 'https://api.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatOpenApp $app,
    ) {
    }

    /**
     * 通用代授权方调用接口（透传）
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function call(
        string $authorizerAccessToken,
        string $path,
        array $params = [],
        string $method = 'POST',
    ): array {
        $url = self::API_BASE . $path;
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . 'access_token=' . $authorizerAccessToken;

        $response = $method === 'GET'
            ? $this->app->http()->get($url, ['query' => $params])
            : $this->app->http()->postJson($url, $params);

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 代小程序登录（code2session）
     *
     * 第三方平台代小程序调用，必须携带有效的 component_access_token（由
     * {@see Component::accessToken()} 换取），缺失会返回 40013 / 40001。
     *
     * @param string $componentAccessToken 第三方平台 component_access_token（必填）
     *
     * @return array<string, mixed> 含 session_key / openid / unionid（绑定开放平台时）
     */
    public function miniProgramSession(
        string $authorizerAppId,
        string $code,
        string $componentAccessToken,
    ): array {
        $url = 'https://api.weixin.qq.com/sns/component/jscode2session';

        $response = $this->app->http()->get($url, [
            'query' => [
                'appid'                  => $authorizerAppId,
                'js_code'                => $code,
                'grant_type'             => 'authorization_code',
                'component_appid'        => $this->app->config()->componentAppId(),
                'component_access_token' => $componentAccessToken,
            ],
        ]);

        return ApiResponse::fromPsr($response, Platform::Wechat)
            ->throwIfFailed('代小程序登录（code2session）')
            ->toArray();
    }

    /**
     * 代公众号创建自定义菜单
     *
     * @param array<int, mixed> $buttons
     * @return array<string, mixed>
     */
    public function createMenu(
        string $authorizerAccessToken,
        array $buttons,
    ): array {
        return $this->call(
            $authorizerAccessToken,
            '/menu/create',
            ['button' => $buttons]
        );
    }

    /**
     * 代公众号查询自定义菜单
     *
     * @return array<string, mixed>
     */
    public function getMenu(string $authorizerAccessToken): array
    {
        return $this->call(
            $authorizerAccessToken,
            '/menu/get',
            [],
            'GET'
        );
    }

    /**
     * 代公众号删除自定义菜单
     *
     * @return array<string, mixed>
     */
    public function deleteMenu(string $authorizerAccessToken): array
    {
        return $this->call(
            $authorizerAccessToken,
            '/menu/delete',
            []
        );
    }

    /**
     * 代公众号发送客服消息
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    public function sendCustomerServiceMessage(
        string $authorizerAccessToken,
        string $openId,
        array $message,
    ): array {
        $payload = array_merge(['touser' => $openId], $message);

        return $this->call(
            $authorizerAccessToken,
            '/message/custom/send',
            $payload
        );
    }

    /**
     * 代公众号 / 小程序上传素材
     *
     * @return array<string, mixed>
     */
    public function uploadMedia(
        string $authorizerAccessToken,
        string $type,
        string $filePath,
        ?string $description = null,
    ): array {
        $url = self::API_BASE . '/media/upload?access_token=' . $authorizerAccessToken
            . '&type=' . $type;

        $multipart = [
            [
                'name'     => 'media',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
            ],
        ];
        if ($description !== null) {
            $multipart[] = ['name' => 'description', 'contents' => $description];
        }

        $response = $this->app->http()->post($url, ['multipart' => $multipart]);

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * 代公众号 / 小程序创建二维码（临时）
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createQrCode(
        string $authorizerAccessToken,
        array $payload,
    ): array {
        return $this->call(
            $authorizerAccessToken,
            '/qrcode/create',
            $payload
        );
    }

    /**
     * 代公众号 / 小程序生成带参数二维码
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createTagQrCode(
        string $authorizerAccessToken,
        array $payload,
    ): array {
        return $this->call(
            $authorizerAccessToken,
            '/tags/members/batchtagging',
            $payload
        );
    }

    /**
     * 代公众号发送模板消息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendTemplateMessage(
        string $authorizerAccessToken,
        string $toUser,
        string $templateId,
        array $data,
        ?string $url = null,
        ?string $miniProgram = null,
    ): array {
        $payload = [
            'touser'      => $toUser,
            'template_id' => $templateId,
            'data'        => $data,
        ];

        if ($url !== null) {
            $payload['url'] = $url;
        }

        if ($miniProgram !== null) {
            $payload['miniprogram'] = json_decode($miniProgram, true) ?? [];
        }

        return $this->call(
            $authorizerAccessToken,
            '/message/template/send',
            $payload
        );
    }

    /**
     * 代小程序发送订阅消息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendSubscribeMessage(
        string $authorizerAccessToken,
        string $toUser,
        string $templateId,
        array $data,
        ?string $page = null,
        ?string $miniprogramState = null,
    ): array {
        $payload = [
            'touser'      => $toUser,
            'template_id' => $templateId,
            'data'        => $data,
        ];

        if ($page !== null) {
            $payload['page'] = $page;
        }

        if ($miniprogramState !== null) {
            $payload['miniprogram_state'] = $miniprogramState;
        }

        return $this->call(
            $authorizerAccessToken,
            '/message/subscribe/send',
            $payload
        );
    }

    /**
     * 代公众号获取用户信息
     *
     * @return array<string, mixed>
     */
    public function getUserInfo(
        string $authorizerAccessToken,
        string $openId,
        string $lang = 'zh_CN',
    ): array {
        return $this->call(
            $authorizerAccessToken,
            '/user/info',
            ['openid' => $openId, 'lang' => $lang],
            'GET'
        );
    }

    /**
     * 代公众号批量获取用户信息
     *
     * @param array<int, string> $openIds
     * @return array<string, mixed>
     */
    public function batchGetUserInfo(
        string $authorizerAccessToken,
        array $openIds,
        string $lang = 'zh_CN',
    ): array {
        $userList = [];
        foreach ($openIds as $openId) {
            $userList[] = ['openid' => $openId, 'lang' => $lang];
        }

        return $this->call(
            $authorizerAccessToken,
            '/user/info/batchget',
            ['user_list' => $userList]
        );
    }
}
