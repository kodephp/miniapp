<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use InvalidArgumentException;
use Kode\MiniApp\Core\SessionKeyManager;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Wechat\WechatApp;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionPhone;

/**
 * Union 层「收敛为 UnionPhone 值对象」的手机号入口测试
 *
 * 覆盖 phoneObjectByCode / phoneObjectByDecrypt / phoneObjectByUser / phoneObjectByResponse
 * 四条入口，验证均返回 UnionPhone 对象且字段正确归一化；不支持渠道抛错行为一致。
 */
class PhoneObjectTest extends TestCase
{
    private const APP_ID = 'wxapp0000000000';

    private function makeUnion(): Union
    {
        return (new Kernel([
            'wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret'],
            'alipay' => ['app_id' => self::APP_ID, 'aes_key' => base64_encode(random_bytes(16))],
        ]))->union();
    }

    /**
     * 微信 AES-128-CBC 加密（与 UserInfoByDecryptTest 同款）
     *
     * @param array<string, mixed> $payload
     * @return array{0:string,1:string,2:string}
     */
    private function encrypt(array $payload): array
    {
        $sessionKey = base64_encode(random_bytes(16));
        $iv         = base64_encode(random_bytes(16));
        $key        = base64_decode($sessionKey, true);
        $vec        = base64_decode($iv, true);
        \assert(is_string($key) && is_string($vec));

        $plain  = json_encode($payload);
        \assert(is_string($plain));
        $cipher = openssl_encrypt($plain, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $vec);
        \assert(is_string($cipher));

        return [base64_encode($cipher), $sessionKey, $iv];
    }

    public function testPhoneObjectByDecryptReturnsUnionPhone(): void
    {
        $union = $this->makeUnion();

        $payload = [
            'phoneNumber'     => '13800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        $phone = $union->phoneObjectByDecrypt(Channel::WechatMini, $encrypted, $sessionKey, $iv);

        self::assertInstanceOf(UnionPhone::class, $phone);
        self::assertSame('13800138000', $phone->phoneNumber);
        self::assertSame('13800138000', $phone->purePhoneNumber);
        self::assertSame('86', $phone->countryCode);
    }

    public function testPhoneObjectByUserReturnsUnionPhone(): void
    {
        $kernel = new Kernel([
            'wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret'],
        ]);
        $union = $kernel->union();
        $wxApp = $kernel->wechat()->app();
        \assert($wxApp instanceof WechatApp);

        $payload = [
            'phoneNumber'     => '13900139000',
            'purePhoneNumber' => '13900139000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::APP_ID, 'timestamp' => 1495788248],
        ];

        [$encrypted, $sessionKey, $iv] = $this->encrypt($payload);
        SessionKeyManager::for($wxApp->config())->store('openid-ph', $sessionKey);

        $phone = $union->phoneObjectByUser(Channel::WechatMini, $encrypted, $iv, 'openid-ph');

        self::assertInstanceOf(UnionPhone::class, $phone);
        self::assertSame('13900139000', $phone->phoneNumber);
    }

    public function testPhoneObjectByResponseReturnsUnionPhone(): void
    {
        $aesKey   = random_bytes(16);
        $union    = (new Kernel([
            'alipay' => ['app_id' => self::APP_ID, 'aes_key' => base64_encode($aesKey)],
        ]))->union();
        $response = $this->encryptAlipay(['mobile' => '13700137000', 'countryCode' => '86'], $aesKey);

        $phone = $union->phoneObjectByResponse(Channel::AlipayMini, $response);

        self::assertInstanceOf(UnionPhone::class, $phone);
        self::assertSame('13700137000', $phone->phoneNumber);
        self::assertSame('13700137000', $phone->purePhoneNumber);
    }

    public function testPhoneObjectByCodeReturnsUnionPhone(): void
    {
        $union = (new Kernel(
            ['wechat' => ['app_id' => self::APP_ID, 'app_secret' => 'app-secret']],
            new class () implements \Kode\MiniApp\Contracts\HttpClientInterface {
                public function get(string $uri, array $options = []): \Psr\Http\Message\ResponseInterface
                {
                    return $this->respond(['access_token' => 'TOKEN_1', 'expires_in' => 7200]);
                }
                public function post(string $uri, array $options = []): \Psr\Http\Message\ResponseInterface
                {
                    return $this->respond([]);
                }
                public function put(string $uri, array $options = []): \Psr\Http\Message\ResponseInterface
                {
                    return $this->respond([]);
                }
                public function patch(string $uri, array $options = []): \Psr\Http\Message\ResponseInterface
                {
                    return $this->respond([]);
                }
                public function delete(string $uri, array $options = []): \Psr\Http\Message\ResponseInterface
                {
                    return $this->respond([]);
                }
                public function postJson(string $uri, array $data = [], array $headers = []): \Psr\Http\Message\ResponseInterface
                {
                    return $this->respond([
                        'errcode'    => 0,
                        'errmsg'     => 'ok',
                        'phone_info' => [
                            'phoneNumber'     => '+8613800138000',
                            'purePhoneNumber' => '13800138000',
                            'countryCode'     => '86',
                            'watermark'       => ['timestamp' => 1637744274, 'appid' => 'wx123'],
                        ],
                    ]);
                }
                public function upload(string $uri, string $field, string $filePath, array $form = []): \Psr\Http\Message\ResponseInterface
                {
                    return $this->respond([]);
                }
                /**
                 * @param array<string, mixed> $body
                 */
                private function respond(array $body): \Psr\Http\Message\ResponseInterface
                {
                    return new class ($body) implements \Psr\Http\Message\ResponseInterface {
                        /**
                         * @param array<string, mixed> $body
                         */
                        public function __construct(private array $body)
                        {
                        }
                        public function getProtocolVersion(): string { return '1.1'; }
                        public function withProtocolVersion($v): self { return $this; }
                        public function getHeaders(): array { return []; }
                        public function hasHeader($n): bool { return false; }
                        public function getHeader($n): array { return []; }
                        public function getHeaderLine($n): string { return ''; }
                        public function withHeader($n, $v): self { return $this; }
                        public function withAddedHeader($n, $v): self { return $this; }
                        public function withoutHeader($n): self { return $this; }
                        public function getBody(): \Psr\Http\Message\StreamInterface
                        {
                            return new class ((string) json_encode($this->body)) implements \Psr\Http\Message\StreamInterface {
                                public function __construct(private string $c) {}
                                public function __toString(): string { return $this->c; }
                                public function close(): void {}
                                public function detach() { return null; }
                                public function getSize(): ?int { return null; }
                                public function tell(): int { return 0; }
                                public function eof(): bool { return true; }
                                public function isSeekable(): bool { return false; }
                                public function seek($o, $w = SEEK_SET): void {}
                                public function rewind(): void {}
                                public function isWritable(): bool { return false; }
                                public function write($s): int { return 0; }
                                public function isReadable(): bool { return true; }
                                public function read($l): string { return ''; }
                                public function getContents(): string { return $this->c; }
                                public function getMetadata($k = null) { return null; }
                            };
                        }
                        public function withBody(\Psr\Http\Message\StreamInterface $b): self { return $this; }
                        public function getStatusCode(): int { return 200; }
                        public function withStatus($c, $r = ''): self { return $this; }
                        public function getReasonPhrase(): string { return 'OK'; }
                    };
                }
            },
        ))->union();

        $phone = $union->phoneObjectByCode(Channel::WechatMini, 'phone-code-1');

        self::assertInstanceOf(UnionPhone::class, $phone);
        self::assertSame('+8613800138000', $phone->phoneNumber);
        self::assertSame('13800138000', $phone->purePhoneNumber);
        self::assertSame('86', $phone->countryCode);
    }

    public function testPhoneObjectByDecryptUnsupportedChannelThrows(): void
    {
        $union = $this->makeUnion();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 encryptedData 解密（手机号 / 用户资料）');
        $union->phoneObjectByDecrypt(Channel::AlipayMini, 'enc', 'sk', 'iv');
    }

    /**
     * 按支付宝官方算法（AES-128-CBC + 全零 IV）加密明文，返回 base64 密文
     *
     * @param array<string, mixed> $payload
     */
    private function encryptAlipay(array $payload, string $aesKeyRaw): string
    {
        $plain = json_encode($payload);
        \assert(is_string($plain));
        $iv     = str_repeat("\0", 16);
        $cipher = openssl_encrypt($plain, 'aes-128-cbc', $aesKeyRaw, OPENSSL_RAW_DATA, $iv);
        \assert(is_string($cipher));

        return base64_encode($cipher);
    }
}
