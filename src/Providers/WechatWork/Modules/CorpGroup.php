<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信上下游/互联企业模块
 */
readonly class CorpGroup
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin/corpgroup';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 获取应用共享信息
     *
     * @return array<string, mixed>
     */
    public function getAppShareInfo(int $agentid = 0): array
    {
        $token = $this->app->auth()->token();
        $data  = [];
        if ($agentid > 0) {
            $data['agentid'] = $agentid;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/corp/get_app_share_info?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取共享信息失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取下级/下游企业小程序session
     *
     * @return array<string, mixed>
     */
    public function unionidToExternalUserid(string $unionid, string $openid, string $corpid = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'unionid' => $unionid,
            'openid'  => $openid,
        ];
        if (!empty($corpid)) {
            $data['corpid'] = $corpid;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/unionid_to_external_userid?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("转换失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 上传临时素材
     *
     * @return array<string, mixed>
     */
    public function uploadImage(string $filename, string $content): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->post(
            self::BASE_URL . "/corp/upload_image?access_token={$token}",
            [
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => $content,
                        'filename' => $filename,
                    ],
                ],
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("上传图片失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
