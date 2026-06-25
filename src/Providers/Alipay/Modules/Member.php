<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;

/**
 * 支付宝会员模块
 */
readonly class Member
{
    private const BASE_URL = 'https://openapi.alipay.com/gateway.do';

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
        $response = $this->request('alipay.user.info.share', [], $accessToken);

        return $response['alipay_user_info_share_response'] ?? [];
    }

    /**
     * 查询支付宝用户授权信息
     *
     * @return array<string, mixed>
     */
    public function authInfo(string $authToken): array
    {
        $response = $this->request('alipay.user.auth.token.query', ['auth_token' => $authToken]);

        return $response['alipay_user_auth_token_query_response'] ?? [];
    }

    /**
     * 查询用户积分余额
     *
     * @return array<string, mixed>
     */
    public function pointBalance(string $userId): array
    {
        $response = $this->request('alipay.user.point.balance.query', ['user_id' => $userId]);

        return $response['alipay_user_point_balance_query_response'] ?? [];
    }

    /**
     * 发送通用请求
     *
     * @param array<string, mixed> $bizContent
     * @return array<string, mixed>
     */
    private function request(string $method, array $bizContent, string $authToken = ''): array
    {
        $config   = $this->app->config();
        $params   = [
            'app_id'     => $config->appId(),
            'method'     => $method,
            'format'     => 'JSON',
            'charset'    => 'utf-8',
            'sign_type'  => 'RSA2',
            'timestamp'  => date('Y-m-d H:i:s'),
            'version'    => '1.0',
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];

        if (!empty($authToken)) {
            $params['auth_token'] = $authToken;
        }

        $params['sign'] = $this->sign($params, $config->get('private_key', ''));

        $response = $this->app->http()->post(self::BASE_URL, $params);

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * RSA2 签名
     *
     * @param array<string, mixed> $params
     */
    private function sign(array $params, string $privateKey): string
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null && $k !== 'sign') {
                $string .= "{$k}={$v}&";
            }
        }
        $string = rtrim($string, '&');

        $key = "-----BEGIN RSA PRIVATE KEY-----\n"
            . wordwrap($privateKey, 64, "\n", true)
            . "\n-----END RSA PRIVATE KEY-----";
        openssl_sign($string, $sign, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($sign);
    }
}
