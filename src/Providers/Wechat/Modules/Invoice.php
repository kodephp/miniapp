<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信发票模块
 */
readonly class Invoice
{
    private const BASE_URL = 'https://api.weixin.qq.com/card/invoice';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 获取授权页链接（用户填写发票抬头）
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function getAuthUrl(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/biz/getauthurl?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取授权页链接失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 获取用户授权填写的抬头信息
     *
     * @return array<string, mixed>
     */
    public function getAuthData(string $orderId, string $sAppid = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = ['order_id' => $orderId];
        if (!empty($sAppid)) {
            $data['s_appid'] = $sAppid;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/biz/getauthdata?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("获取授权数据失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 拒绝开具发票
     *
     * @return array<string, mixed>
     */
    public function rejectInsert(string $orderId, string $reason, string $url = ''): array
    {
        $token = $this->app->auth()->token();
        $data  = [
            'order_id' => $orderId,
            'reason'   => $reason,
        ];
        if (!empty($url)) {
            $data['url'] = $url;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/biz/rejectinsert?access_token={$token}",
            $data
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("拒绝开票失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 开具发票（将发票插入用户卡包）
     *
     * @param array<string, mixed> $invoiceData
     * @return array<string, mixed>
     */
    public function makeOutInvoice(array $invoiceData): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/makeoutinvoice?access_token={$token}",
            $invoiceData
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("开具发票失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 查询发票信息
     *
     * @return array<string, mixed>
     */
    public function queryInvoiceInfo(string $cardId, string $encryptCode): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/biz/getinvoiceinfo?access_token={$token}",
            [
                'card_id'      => $cardId,
                'encrypt_code' => $encryptCode,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("查询发票失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 查询报销发票信息（批量）
     *
     * @param array<int, array<string, string>> $itemList
     * @return array<string, mixed>
     */
    public function queryInvoiceBatch(array $itemList): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/biz/getinvoicebatch?access_token={$token}",
            ['item_list' => $itemList]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("批量查询发票失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }

    /**
     * 更新发票状态（报销）
     *
     * @return array<string, mixed>
     */
    public function updateStatus(string $cardId, string $encryptCode, string $reimburseStatus): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/biz/reimburse/updatestatus?access_token={$token}",
            [
                'card_id'          => $cardId,
                'encrypt_code'     => $encryptCode,
                'reimburse_status' => $reimburseStatus,
            ]
        );
        $result = json_decode((string) $response->getBody(), true);

        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \RuntimeException("更新发票状态失败: [{$result['errcode']}] {$result['errmsg']}");
        }

        return $result;
    }
}
