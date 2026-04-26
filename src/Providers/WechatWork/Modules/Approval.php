<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信审批模块
 */
readonly class Approval
{
    private const string BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 获取审批模板详情
     *
     * @return array<string, mixed>
     */
    public function template(string $templateId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/gettemplatedetail?access_token={$token}",
            ['template_id' => $templateId]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取审批模板失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 提交审批申请
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function apply(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/applyevent?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询审批申请状态
     *
     * @return array<string, mixed>
     */
    public function detail(string $spNo): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/oa/getapprovaldetail?access_token={$token}",
            ['sp_no' => $spNo]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("查询审批详情失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }
}
