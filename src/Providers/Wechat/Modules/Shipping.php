<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信小程序订单物流同步模块
 * 支持发货信息录入、发货信息合单、查询发货信息、确认收货提醒等
 */
readonly class Shipping
{
    private const BASE_URL = 'https://api.weixin.qq.com/wxa/sec/order';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * 发货信息录入接口
     * 用于小程序发货信息的上报，同步订单物流状态
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function uploadShippingInfo(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/upload_shipping_info?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 发货信息合单接口
     * 将多个订单合并为一个物流单号进行发货
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function uploadCombinedShippingInfo(array $params): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/upload_combined_shipping_info?access_token={$token}",
            $params
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 查询小程序发货信息
     *
     * @return array<string, mixed>
     */
    public function getOrder(string $transactionId = '', string $merchantId = '', string $subMerchantId = ''): array
    {
        $token    = $this->app->auth()->token();
        $data     = [];
        if (!empty($transactionId)) {
            $data['transaction_id'] = $transactionId;
        }
        if (!empty($merchantId)) {
            $data['merchant_id'] = $merchantId;
        }
        if (!empty($subMerchantId)) {
            $data['sub_merchant_id'] = $subMerchantId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/get_order?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 确认收货提醒接口
     * 向用户推送确认收货提醒
     *
     * @return array<string, mixed>
     */
    public function notifyConfirmReceive(string $transactionId = '', string $merchantId = ''): array
    {
        $token    = $this->app->auth()->token();
        $data     = [];
        if (!empty($transactionId)) {
            $data['transaction_id'] = $transactionId;
        }
        if (!empty($merchantId)) {
            $data['merchant_id'] = $merchantId;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/notify_confirm_receive?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 设置消息跳转路径
     * 设置用户确认收货消息的跳转路径
     *
     * @return array<string, mixed>
     */
    public function setMsgJumpPath(string $path): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/set_msg_jump_path?access_token={$token}",
            ['path' => $path]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 快捷方法：标准快递发货
     *
     * @param array<int, array<string, mixed>> $shippingList
     * @return array<string, mixed>
     */
    public function express(
        string $orderKey,
        string $orderKeyMode,
        array $shippingList,
        int $payerOpenid = 0,
        string $logisticsType = '3',
        string $deliveryMode = '1',
    ): array {
        return $this->uploadShippingInfo([
            'order_key'       => [
                'order_number_type' => $orderKeyMode,
                'mchid'             => $this->app->config()->get('mch_id'),
                'transaction_id'    => $orderKeyMode === '2' ? $orderKey : '',
                'merchant_trade_no' => $orderKeyMode === '1' ? $orderKey : '',
            ],
            'logistics_type'  => $logisticsType,
            'delivery_mode'   => $deliveryMode,
            'shipping_list'   => $shippingList,
            'upload_time'     => date('Y-m-d\TH:i:sP'),
            'payer'           => ['openid' => $payerOpenid],
        ]);
    }

    /**
     * 快捷方法：无需物流发货（虚拟商品/服务）
     *
     * @return array<string, mixed>
     */
    public function noShipping(
        string $orderKey,
        string $orderKeyMode,
        int $payerOpenid = 0,
    ): array {
        return $this->uploadShippingInfo([
            'order_key'      => [
                'order_number_type' => $orderKeyMode,
                'mchid'             => $this->app->config()->get('mch_id'),
                'transaction_id'    => $orderKeyMode === '2' ? $orderKey : '',
                'merchant_trade_no' => $orderKeyMode === '1' ? $orderKey : '',
            ],
            'logistics_type' => '4',
            'shipping_list'  => [],
            'upload_time'    => date('Y-m-d\TH:i:sP'),
            'payer'          => ['openid' => $payerOpenid],
        ]);
    }

    /**
     * 快捷方法：同城配送发货
     *
     * @param array<int, array<string, mixed>> $shippingList
     * @return array<string, mixed>
     */
    public function sameCity(
        string $orderKey,
        string $orderKeyMode,
        array $shippingList,
        int $payerOpenid = 0,
    ): array {
        return $this->uploadShippingInfo([
            'order_key'      => [
                'order_number_type' => $orderKeyMode,
                'mchid'             => $this->app->config()->get('mch_id'),
                'transaction_id'    => $orderKeyMode === '2' ? $orderKey : '',
                'merchant_trade_no' => $orderKeyMode === '1' ? $orderKey : '',
            ],
            'logistics_type' => '2',
            'shipping_list'  => $shippingList,
            'upload_time'    => date('Y-m-d\TH:i:sP'),
            'payer'          => ['openid' => $payerOpenid],
        ]);
    }

    /**
     * 快捷方法：用户自提发货
     *
     * @param array<int, array<string, mixed>> $shippingList
     * @return array<string, mixed>
     */
    public function selfPickup(
        string $orderKey,
        string $orderKeyMode,
        array $shippingList,
        int $payerOpenid = 0,
    ): array {
        return $this->uploadShippingInfo([
            'order_key'      => [
                'order_number_type' => $orderKeyMode,
                'mchid'             => $this->app->config()->get('mch_id'),
                'transaction_id'    => $orderKeyMode === '2' ? $orderKey : '',
                'merchant_trade_no' => $orderKeyMode === '1' ? $orderKey : '',
            ],
            'logistics_type' => '5',
            'shipping_list'  => $shippingList,
            'upload_time'    => date('Y-m-d\TH:i:sP'),
            'payer'          => ['openid' => $payerOpenid],
        ]);
    }
}
