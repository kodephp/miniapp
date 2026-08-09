<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay\Modules;

use Kode\MiniApp\Core\PhoneNormalizer;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Utils\Sign;

/**
 * 支付宝小程序客户端敏感数据解密（手机号 / 资料）
 *
 * 支付宝与微信 / 抖音 / QQ 的解密算法不同：
 *   - 密钥 key   = base64_decode(config aes_key)，长度固定 16 字节（AES-128）
 *   - 向量 iv    = 16 字节全零（\0）
 *   - 密文       = base64_decode(response)
 *   - 对称算法   = AES-128-CBC，PKCS#7 填充（openssl 解密时自动去除）
 *   - 明文为 JSON，结构内含 mobile / countryCode 等字段
 *
 * 安全约束：
 *   - 强烈建议传入 sign 进行 RSA2 验签（防中间人篡改），验签公钥为 config public_key。
 *   - aes_key 属敏感凭证，严禁写入日志（LogSanitizer 已覆盖脱敏）。
 */
final class Decrypt
{
    public function __construct(
        private AlipayApp $app,
    ) {
    }

    /**
     * 解密 response，返回原始数组
     *
     * @throws ApiException 解密失败、结果非 JSON 或 aes_key 配置非法
     * @return array<string, mixed>
     */
    public function data(string $response): array
    {
        $key = base64_decode($this->app->config()->aesKey(), true);
        if ($key === false || strlen($key) !== 16) {
            throw new ApiException('支付宝敏感数据解密失败：aes_key 配置非法（需 16 字节 base64 编码）', -1);
        }

        $data = base64_decode($response, true);
        if ($data === false) {
            throw new ApiException('支付宝敏感数据解密失败：base64 解析错误', -1);
        }

        $iv        = str_repeat("\0", 16);
        $decrypted = openssl_decrypt($data, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false || $decrypted === '') {
            throw new ApiException('支付宝敏感数据解密失败：AES 解密错误', -1);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($decrypted, true);
        if (!is_array($payload)) {
            throw new ApiException('支付宝敏感数据解密失败：结果不是合法 JSON', -1);
        }

        return $payload;
    }

    /**
     * 解密手机号（my.getPhoneNumber）
     *
     * 若传入 sign，则先以 config public_key 做 RSA2 验签，验签失败直接抛异常。
     * 返回数组含 mobile / countryCode 等字段。
     *
     * @throws ApiException 验签失败、解密失败或缺少手机号字段
     * @return array<string, mixed>
     */
    public function phone(string $response, ?string $sign = null): array
    {
        if ($sign !== null && $sign !== '') {
            if (!$this->verifySign($response, $sign)) {
                throw new ApiException('支付宝手机号解密失败：RSA2 验签不通过', -1);
            }
        }

        $data = $this->data($response);

        if (!isset($data['mobile']) || !is_string($data['mobile'])) {
            throw new ApiException('支付宝手机号解密结果缺少字段：mobile', -1);
        }

        // 归一化：在保留原始 mobile 字段的同时，补充与其他端一致的
        // phoneNumber / purePhoneNumber / countryCode，便于业务侧统一消费。
        return array_merge($data, PhoneNormalizer::normalize($data));
    }

    /**
     * 使用 config public_key 对 response 做 RSA2 验签
     *
     * @throws ApiException 公钥未配置
     */
    public function verifySign(string $response, string $sign): bool
    {
        $publicKey = $this->app->config()->publicKey();
        if ($publicKey === '') {
            throw new ApiException('支付宝公钥未配置（public_key），无法验签', -1);
        }

        return Sign::verifyRsaRaw($response, $publicKey, $sign, 'sha256');
    }
}
