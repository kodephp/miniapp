<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Utils\Str;

/**
 * 支付宝登录/授权模块
 */
readonly class Auth
{
    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 获取 AccessToken
     *
     * @return array<string, mixed>
     */
    public function token(string $code): array
    {
        $params = $this->buildParams('alipay.system.oauth.token', [
            'grant_type' => 'authorization_code',
            'code'       => $code,
        ]);

        $response = $this->app->http()->post($this->app->config()->get('gateway'), ['form_params' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        return $data['alipay_system_oauth_token_response'] ?? [];
    }

    /**
     * 获取用户信息
     *
     * @return array<string, mixed>
     */
    public function user(string $accessToken): array
    {
        $params = $this->buildParams('alipay.user.info.share', [
            'auth_token' => $accessToken,
        ]);

        $response = $this->app->http()->post($this->app->config()->get('gateway'), ['form_params' => $params]);
        $data     = json_decode((string) $response->getBody(), true);

        return $data['alipay_user_info_share_response'] ?? [];
    }

    /**
     * 构造公共参数
     *
     * @param array<string, mixed> $biz
     * @return array<string, mixed>
     */
    private function buildParams(string $method, array $biz = []): array
    {
        $config = $this->app->config();
        $params = [
            'app_id'     => $config->appId(),
            'method'     => $method,
            'format'     => 'JSON',
            'charset'    => 'utf-8',
            'sign_type'  => 'RSA2',
            'timestamp'  => date('Y-m-d H:i:s'),
            'version'    => '1.0',
            'biz_content' => json_encode($biz),
        ];

        $params['sign'] = $this->sign($params, $config->get('private_key'));

        return $params;
    }

    /**
     * RSA2 签名
     *
     * @param array<string, mixed> $params
     */
    private function sign(array $params, string $privateKey): string
    {
        ksort($params);
        $string = http_build_query($params);
        $string = urldecode($string);

        $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
               wordwrap($privateKey, 64, "\n", true) .
               "\n-----END RSA PRIVATE KEY-----";

        openssl_sign($string, $sign, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($sign);
    }
}
