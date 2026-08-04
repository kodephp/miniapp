<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Providers\Alipay\AlipayGateway;

/**
 * 支付宝会员模块
 */
readonly class Member
{
    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 获取会员信息
     *
     * @return array<string, mixed>
     */
    public function info(string $accessToken): array
    {
        return $this->request('alipay.user.info.share', [], $accessToken);
    }

    /**
     * 查询支付宝用户授权信息
     *
     * @return array<string, mixed>
     */
    public function authInfo(string $authToken): array
    {
        return $this->request('alipay.user.auth.token.query', [], $authToken);
    }

    /**
     * 查询用户积分余额
     *
     * @return array<string, mixed>
     */
    public function pointBalance(string $userId): array
    {
        return $this->request('alipay.user.point.balance.query', ['user_id' => $userId]);
    }

    /**
     * 发送通用请求并归一化提取响应节点
     *
     * auth_token 属于顶层请求参数，需通过 $extra 传入网关，
     * 而非塞进 biz_content。
     *
     * @param array<string, mixed> $bizContent
     * @return array<string, mixed>
     */
    private function request(string $method, array $bizContent, string $authToken = ''): array
    {
        $extra = $authToken !== '' ? ['auth_token' => $authToken] : [];

        return $this->app->gateway()
            ->execute($method, $bizContent, $extra)
            ->throwIfFailed('支付宝会员接口调用')
            ->array(AlipayGateway::responseNode($method));
    }
}
