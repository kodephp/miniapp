<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Wechat\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\Wechat\WechatApp;

/**
 * 微信小程序手机号快速验证（新版 code 换手机号）
 *
 * 自基础库 2.21.2 起，`<button open-type="getPhoneNumber">` 回调返回的是**动态令牌 code**
 * （而非 encryptedData + iv），须由服务端消费 code 换取手机号，不再依赖 session_key。
 *
 *   前端：e.detail.code  →  服务端：$app->phone()->byCode($code)
 *
 * 与 {@see Decrypt::phone()}（旧版 encryptedData 解密）互为两条并行路径，旧方式仍可用，
 * 但官方建议使用本方式以增强安全性。
 *
 * 约束：
 *   - 每个 code 仅可消费一次，有效期 5 分钟。
 *   - code 与 wx.login 返回的 code 作用不同，不可混用（混用报 40029）。
 *   - 该能力对非个人主体且已认证的小程序开放，且按次计费。
 */
final class Phone
{
    private const string BASE_URL = 'https://api.weixin.qq.com/wxa/business';

    public function __construct(
        private WechatApp $app,
    ) {
    }

    /**
     * code 换取手机号，返回完整 phone_info
     *
     * 返回数组含 phoneNumber / purePhoneNumber / countryCode / watermark 字段。
     *
     * @param string      $code   前端 bindgetphonenumber 回调中的动态令牌
     * @param string|null $openId 可选，用户 openid（官方选填参数）
     *
     * @throws ApiException code 为空、接口返回错误码或响应结构异常
     * @return array<string, mixed>
     */
    public function byCode(string $code, ?string $openId = null): array
    {
        if (trim($code) === '') {
            throw new ApiException('微信手机号换取失败：code 不能为空', -1);
        }

        $payload = ['code' => $code];
        if ($openId !== null && $openId !== '') {
            $payload['openid'] = $openId;
        }

        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/getuserphonenumber?access_token={$token}",
            $payload,
        );

        $data = ApiResponse::fromPsr($response, Platform::Wechat)
            ->throwIfFailed('获取手机号')
            ->toArray();

        $info = $data['phone_info'] ?? null;
        if (!is_array($info)) {
            throw new ApiException('微信手机号换取失败：响应缺少 phone_info', -1);
        }

        foreach (['phoneNumber', 'purePhoneNumber', 'countryCode'] as $key) {
            if (!isset($info[$key]) || !is_scalar($info[$key])) {
                throw new ApiException("微信手机号换取结果缺少字段：{$key}", -1);
            }
        }

        /** @var array<string, mixed> $info */
        return $info;
    }

    /**
     * code 换取手机号，直接返回带区号的手机号字符串
     *
     * @throws ApiException 换取失败
     */
    public function numberByCode(string $code, ?string $openId = null): string
    {
        return (string) $this->byCode($code, $openId)['phoneNumber'];
    }

    /**
     * code 换取手机号，直接返回不带区号的纯手机号字符串
     *
     * @throws ApiException 换取失败
     */
    public function pureNumberByCode(string $code, ?string $openId = null): string
    {
        return (string) $this->byCode($code, $openId)['purePhoneNumber'];
    }
}
