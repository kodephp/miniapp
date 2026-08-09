<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Douyin\Modules;

use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Core\Crypto\RsaPkcs1;
use Kode\MiniApp\Core\TokenManager;
use Kode\MiniApp\Core\TokenResult;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\Douyin\DouyinApp;

/**
 * 抖音小程序手机号（getPhoneNumber 组件 code 换取手机号）
 *
 * 自基础库 3.51.0 起，`<button open-type="getPhoneNumber">` 回调除 encryptedData + iv
 * 外还返回**动态令牌 code**，可由服务端消费 code 直接换取手机号，不再依赖 session_key：
 *
 *   前端：e.detail.code  →  服务端：$app->phone()->byCode($code)
 *
 * 与微信不同，抖音该接口返回的是**用应用公钥 RSA 加密的密文**，需用开发者自己保管的
 * 应用私钥解密（配置项 `app_private_key`），而非明文 JSON：
 *
 *   - 凭证：client_token（开放平台 open.douyin.com 体系），非小程序侧 access_token
 *   - 密文：base64(RSA-PKCS#1v15(明文))，见 {@see RsaPkcs1}
 *   - 明文：{phoneNumber, purePhoneNumber, countryCode, watermark:{appid, timestamp}}
 *
 * 与 {@see Decrypt::phone()}（encryptedData + session_key 对称解密）互为两条并行路径。
 *
 * 约束：
 *   - 每个 code 仅可消费一次，过期后报 28005187。
 *   - 该 code 与 tt.login 返回的 code 作用不同，不可混用。
 *   - 需在「抖音开放平台-控制台-开发-开发配置-应用公钥」录入公钥，私钥自行保管。
 *     平台更新应用公钥后会立即改用新公钥加密，须同步更新 `app_private_key`。
 */
final class Phone
{
    private const string TOKEN_URL   = 'https://open.douyin.com/oauth/client_token/';
    private const string PHONE_URL   = 'https://open.douyin.com/api/apps/v1/get_phonenumber_info/';
    private const string TOKEN_SCOPE = 'client_token';

    public function __construct(
        private DouyinApp $app,
    ) {
    }

    /**
     * code 换取手机号，返回解密后的完整信息
     *
     * 返回数组含 phoneNumber / purePhoneNumber / countryCode / watermark 字段。
     *
     * @param string $code 前端 getPhoneNumber 回调中的动态令牌
     *
     * @throws ApiException code 为空、未配置应用私钥、接口报错、解密失败或水印校验不通过
     * @return array<string, mixed>
     */
    public function byCode(string $code): array
    {
        if (trim($code) === '') {
            throw new ApiException('抖音手机号换取失败：code 不能为空', -1);
        }

        $privateKey = (string) $this->app->config()->get('app_private_key', '');
        if (trim($privateKey) === '') {
            throw new ApiException('抖音手机号换取失败：未配置 app_private_key（应用私钥）', -1);
        }

        $response = $this->app->http()->postJson(
            self::PHONE_URL,
            ['code' => $code],
            ['access-token' => $this->clientToken()],
        );

        $cipher = ApiResponse::fromPsr($response, Platform::Douyin)
            ->throwIfFailed('获取手机号')
            ->string('data');

        if ($cipher === '') {
            throw new ApiException('抖音手机号换取失败：响应缺少密文数据', -1);
        }

        $info = RsaPkcs1::decryptJson($cipher, $privateKey);
        $this->assertWatermark($info);

        foreach (['phoneNumber', 'purePhoneNumber', 'countryCode'] as $key) {
            if (!isset($info[$key]) || !is_scalar($info[$key])) {
                throw new ApiException("抖音手机号换取结果缺少字段：{$key}", -1);
            }
        }

        return $info;
    }

    /**
     * code 换取手机号，直接返回带区号的手机号字符串
     *
     * @throws ApiException 换取失败
     */
    public function numberByCode(string $code): string
    {
        return (string) $this->byCode($code)['phoneNumber'];
    }

    /**
     * code 换取手机号，直接返回不带区号的纯手机号字符串
     *
     * @throws ApiException 换取失败
     */
    public function pureNumberByCode(string $code): string
    {
        return (string) $this->byCode($code)['purePhoneNumber'];
    }

    /**
     * 获取 client_token（默认命中缓存）
     *
     * client_token 属开放平台体系，用于无需用户授权的接口，与
     * {@see Auth::token()} 的小程序 access_token 是两套独立凭证。
     */
    public function clientToken(bool $forceRefresh = false): string
    {
        $manager = TokenManager::for($this->app->config());

        $token = $forceRefresh
            ? $manager->refresh(Platform::Douyin, $this->identity(), self::TOKEN_SCOPE, $this->resolver())
            : $manager->remember(Platform::Douyin, $this->identity(), self::TOKEN_SCOPE, $this->resolver());

        return is_string($token) ? $token : '';
    }

    /**
     * 强制刷新 client_token
     */
    public function refreshClientToken(): string
    {
        return $this->clientToken(true);
    }

    /**
     * 清除 client_token 缓存
     */
    public function forgetClientToken(): void
    {
        TokenManager::for($this->app->config())
            ->forget(Platform::Douyin, $this->identity(), self::TOKEN_SCOPE);
    }

    /**
     * 校验水印所属小程序，防止跨应用重放
     *
     * @param array<string, mixed> $info
     *
     * @throws ApiException 水印缺失或与当前小程序不符
     */
    private function assertWatermark(array $info): void
    {
        $watermark = $info['watermark'] ?? null;
        if (!is_array($watermark)) {
            throw new ApiException('抖音手机号换取失败：缺少 watermark 节点', -1);
        }

        $appId = $watermark['appid'] ?? null;
        if (!is_string($appId) || $appId !== $this->app->config()->appId()) {
            throw new ApiException('抖音手机号换取失败：watermark.appid 校验不通过', -1);
        }
    }

    private function identity(): string
    {
        $config = $this->app->config();

        return $config->appId() . '|' . $config->secret();
    }

    /**
     * @return callable(): TokenResult
     */
    private function resolver(): callable
    {
        return function (): TokenResult {
            $config   = $this->app->config();
            $response = $this->app->http()->postJson(self::TOKEN_URL, [
                'grant_type'    => 'client_credential',
                'client_key'    => $config->appId(),
                'client_secret' => $config->secret(),
            ]);

            $api = ApiResponse::fromPsr($response, Platform::Douyin)
                ->throwIfFailed('获取 ClientToken');

            return new TokenResult(
                $api->string('data.access_token'),
                $api->int('data.expires_in', TokenResult::DEFAULT_EXPIRES_IN)
            );
        };
    }
}
