<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Utils\Sign;

/**
 * 支付宝开放平台网关调用器
 *
 * 统一收敛公共参数拼装、RSA2 签名、请求发送与响应解析，
 * 替代此前散落在各业务模块中的 6 份重复 buildParams()/sign() 实现。
 *
 * 关键修正（v1.14.0）：
 * - 签名串按支付宝规范剔除空值与 sign 字段，此前未过滤会导致验签失败；
 * - grant_type / code / auth_token 等属于「顶层请求参数」而非 biz_content，
 *   此前错误地塞进 biz_content 会导致 alipay.system.oauth.token 调用失败；
 * - 私钥同时兼容 PKCS#1 与 PKCS#8。
 */
final readonly class AlipayGateway
{
    public const string DEFAULT_GATEWAY = 'https://openapi.alipay.com/gateway.do';
    public const string SIGN_TYPE       = 'RSA2';
    public const string VERSION         = '1.0';
    public const string CHARSET         = 'utf-8';

    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 发起网关请求并返回统一响应对象
     *
     * @param array<string, mixed> $biz   业务参数（写入 biz_content）
     * @param array<string, mixed> $extra 顶层附加参数（grant_type、code、auth_token 等）
     */
    public function execute(string $method, array $biz = [], array $extra = []): ApiResponse
    {
        $response = $this->app->http()->post($this->gateway(), [
            'form_params' => $this->buildParams($method, $biz, $extra),
        ]);

        return ApiResponse::fromPsr($response, Platform::Alipay);
    }

    /**
     * 构造完整请求参数（含签名）
     *
     * @param array<string, mixed> $biz
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function buildParams(string $method, array $biz = [], array $extra = []): array
    {
        $config = $this->app->config();

        $params = [
            'app_id'    => $config->appId(),
            'method'    => $method,
            'format'    => 'JSON',
            'charset'   => self::CHARSET,
            'sign_type' => self::SIGN_TYPE,
            'timestamp' => date('Y-m-d H:i:s'),
            'version'   => self::VERSION,
        ];

        $notifyUrl = $config->get('notify_url', '');
        if (is_string($notifyUrl) && $notifyUrl !== '') {
            $params['notify_url'] = $notifyUrl;
        }

        $appAuthToken = $config->get('app_auth_token', '');
        if (is_string($appAuthToken) && $appAuthToken !== '') {
            $params['app_auth_token'] = $appAuthToken;
        }

        foreach ($extra as $key => $value) {
            if ($value !== null && $value !== '') {
                $params[$key] = $value;
            }
        }

        if ($biz !== []) {
            $params['biz_content'] = (string) json_encode($biz, JSON_UNESCAPED_UNICODE);
        }

        $params['sign'] = Sign::rsaRaw(
            self::signContent($params),
            (string) $config->get('private_key', '')
        );

        return $params;
    }

    /**
     * 验证支付宝回调 / 响应签名
     *
     * @param array<string, mixed> $params 含 sign、sign_type 的原始参数
     */
    public function verify(array $params): bool
    {
        $sign     = (string) ($params['sign'] ?? '');
        $signType = (string) ($params['sign_type'] ?? self::SIGN_TYPE);
        unset($params['sign'], $params['sign_type']);

        $publicKey = (string) $this->app->config()->get('public_key', '');
        if ($publicKey === '' || $sign === '') {
            return false;
        }

        return Sign::verifyRsaRaw(
            self::signContent($params),
            $publicKey,
            $sign,
            $signType === 'RSA' ? 'sha1' : 'sha256'
        );
    }

    /**
     * 生成待签名串：按 key 升序，剔除空值与 sign 字段
     *
     * @param array<string, mixed> $params
     */
    public static function signContent(array $params): string
    {
        unset($params['sign']);
        ksort($params);

        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $pairs[] = $key . '=' . (is_bool($value) ? var_export($value, true) : (string) $value);
        }

        return implode('&', $pairs);
    }

    /**
     * 根据接口名推导响应节点名
     *
     * alipay.trade.create => alipay_trade_create_response
     */
    public static function responseNode(string $method): string
    {
        return str_replace('.', '_', $method) . '_response';
    }

    /**
     * 网关地址（支持沙箱）
     */
    public function gateway(): string
    {
        $gateway = $this->app->config()->get('gateway', self::DEFAULT_GATEWAY);

        return is_string($gateway) && $gateway !== '' ? $gateway : self::DEFAULT_GATEWAY;
    }
}
