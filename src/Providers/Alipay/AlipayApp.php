<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Alipay;

use Kode\MiniApp\Bridge\PayBridge;
use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Notify\Notify;
use Kode\MiniApp\Providers\Alipay\AlipayConfig;
use Kode\MiniApp\Providers\Alipay\Modules\Auth;
use Kode\MiniApp\Providers\Alipay\Modules\Decrypt;
use Kode\MiniApp\Providers\Alipay\Modules\Bill;
use Kode\MiniApp\Providers\Alipay\Modules\Marketing;
use Kode\MiniApp\Providers\Alipay\Modules\Member;
use Kode\MiniApp\Providers\Alipay\Modules\Transfer;

/**
 * 支付宝应用实例
 * 聚合支付宝所有能力模块
 * 支付能力优先桥接到 kode/pays（如已安装）
 */
final readonly class AlipayApp implements AppInterface
{
    private Auth $auth;
    private Decrypt $decrypt;
    private Transfer $transfer;
    private Bill $bill;
    private Marketing $marketing;
    private Member $member;
    private AlipayGateway $gateway;

    public function __construct(
        private string $name,
        private PlatformInterface $platform,
        private AlipayConfig $config,
        private HttpClientInterface $http,
    ) {
        $this->gateway  = new AlipayGateway($this);
        $this->auth     = new Auth($this);
        $this->decrypt  = new Decrypt($this);
        $this->transfer = new Transfer($this);
        $this->bill     = new Bill($this);
        $this->marketing = new Marketing($this);
        $this->member   = new Member($this);
    }

    #[\Override]
    public function name(): string
    {
        return $this->name;
    }

    #[\Override]
    public function platform(): PlatformInterface
    {
        return $this->platform;
    }

    #[\Override]
    public function config(): AlipayConfig
    {
        return $this->config;
    }

    #[\Override]
    public function http(): HttpClientInterface
    {
        return $this->http;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    /**
     * 客户端加密数据解密（手机号 / 资料）
     *
     * 支付宝与微信/抖音/QQ 算法不同：使用开放平台配置的 AES 密钥（config aes_key），
     * IV 固定为 16 字节全零，密文为 base64 编码，解密后 JSON 含 mobile 字段。
     */
    public function decrypt(): Decrypt
    {
        return $this->decrypt;
    }

    /**
     * 获取网关调用器（公共参数拼装 + RSA2 签名 + 验签）
     */
    public function gateway(): AlipayGateway
    {
        return $this->gateway;
    }

    /**
     * 获取企业级支付实例（需安装 kode/pays）
     */
    public function payBridge(): ?object
    {
        return PayBridge::getPay($this);
    }

    public function transfer(): Transfer
    {
        return $this->transfer;
    }

    public function bill(): Bill
    {
        return $this->bill;
    }

    public function marketing(): Marketing
    {
        return $this->marketing;
    }

    public function member(): Member
    {
        return $this->member;
    }

    public function notify(): Notify
    {
        return new Notify($this);
    }
}
