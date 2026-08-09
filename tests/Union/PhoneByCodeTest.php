<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use InvalidArgumentException;
use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Union::phoneByCode() 统一 code 换手机号测试
 *
 * 覆盖微信小程序（明文 phone_info）与抖音小程序（RSA 密文自动解密）两种分派、
 * openid 透传差异，以及不支持渠道的显式抛错。
 */
class PhoneByCodeTest extends TestCase
{
    private const string DOUYIN_APP_ID = 'tt1234567890';

    private string $privateKey = '';
    private string $publicKey  = '';

    protected function setUp(): void
    {
        parent::setUp();

        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($res, '生成测试密钥对失败');

        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);

        $this->privateKey = (string) $privateKey;
        $this->publicKey  = is_array($details) ? (string) $details['key'] : '';
    }

    /**
     * 模拟抖音服务端：应用公钥 RSA 加密 + base64
     */
    private function douyinCipher(): string
    {
        $plain = (string) json_encode([
            'phoneNumber'     => '+8613900139000',
            'purePhoneNumber' => '13900139000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => self::DOUYIN_APP_ID, 'timestamp' => 1637744274],
        ]);

        $block = '';
        self::assertTrue(openssl_public_encrypt($plain, $block, $this->publicKey, OPENSSL_PKCS1_PADDING));

        return base64_encode($block);
    }

    private function buildKernel(\stdClass $capture): Kernel
    {
        $stub = new class ($capture, $this->douyinCipher()) implements HttpClientInterface {
            public function __construct(
                private \stdClass $capture,
                private string $douyinCipher,
            ) {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond(['access_token' => 'TOKEN_1', 'expires_in' => 7200]);
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
            }

            public function put(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
            }

            public function patch(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
            }

            public function delete(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
            }

            public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
            {
                if (str_contains($uri, 'oauth/client_token')) {
                    return $this->respond([
                        'data' => [
                            'access_token' => 'clt.CLIENT_TOKEN_1',
                            'expires_in'   => 7200,
                            'error_code'   => 0,
                        ],
                    ]);
                }

                $this->capture->uri  = $uri;
                $this->capture->data = $data;

                if (str_contains($uri, 'get_phonenumber_info')) {
                    return $this->respond([
                        'data'     => $this->douyinCipher,
                        'err_no'   => 0,
                        'err_tips' => 'success',
                    ]);
                }

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

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return $this->respond([]);
            }

            /**
             * @param array<string, mixed> $body
             */
            private function respond(array $body): ResponseInterface
            {
                return new class ($body) implements ResponseInterface {
                    /**
                     * @param array<string, mixed> $body
                     */
                    public function __construct(private array $body)
                    {
                    }

                    public function getProtocolVersion(): string
                    {
                        return '1.1';
                    }
                    public function withProtocolVersion($v): self
                    {
                        return $this;
                    }
                    public function getHeaders(): array
                    {
                        return [];
                    }
                    public function hasHeader($n): bool
                    {
                        return false;
                    }
                    public function getHeader($n): array
                    {
                        return [];
                    }
                    public function getHeaderLine($n): string
                    {
                        return '';
                    }
                    public function withHeader($n, $v): self
                    {
                        return $this;
                    }
                    public function withAddedHeader($n, $v): self
                    {
                        return $this;
                    }
                    public function withoutHeader($n): self
                    {
                        return $this;
                    }
                    public function getBody(): StreamInterface
                    {
                        return new class ($this->body) implements StreamInterface {
                            /**
                             * @param array<string, mixed> $body
                             */
                            public function __construct(private array $body)
                            {
                            }
                            public function __toString(): string
                            {
                                return (string) json_encode($this->body);
                            }
                            public function close(): void
                            {
                            }
                            public function detach()
                            {
                                return null;
                            }
                            public function getSize(): ?int
                            {
                                return null;
                            }
                            public function tell(): int
                            {
                                return 0;
                            }
                            public function eof(): bool
                            {
                                return true;
                            }
                            public function isSeekable(): bool
                            {
                                return false;
                            }
                            public function seek($o, $w = SEEK_SET): void
                            {
                            }
                            public function rewind(): void
                            {
                            }
                            public function isWritable(): bool
                            {
                                return false;
                            }
                            public function write($s): int
                            {
                                return 0;
                            }
                            public function isReadable(): bool
                            {
                                return true;
                            }
                            public function read($l): string
                            {
                                return '';
                            }
                            public function getContents(): string
                            {
                                return (string) json_encode($this->body);
                            }
                            public function getMetadata($k = null)
                            {
                                return null;
                            }
                        };
                    }
                    public function withBody(StreamInterface $b): self
                    {
                        return $this;
                    }
                    public function getStatusCode(): int
                    {
                        return 200;
                    }
                    public function withStatus($c, $r = ''): self
                    {
                        return $this;
                    }
                    public function getReasonPhrase(): string
                    {
                        return 'OK';
                    }
                };
            }
        };

        return new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx123',
                    'secret' => 's3cr3t',
                    'cache'  => new ArrayCache(),
                ],
                'douyin' => [
                    'app_id'          => self::DOUYIN_APP_ID,
                    'app_secret'      => 'app-secret',
                    'app_private_key' => $this->privateKey,
                    'cache'           => new ArrayCache(),
                ],
            ],
            $stub,
        );
    }

    public function testUnionPhoneByCodeWechatMini(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        $info = $union->phoneByCode(Channel::WechatMini, 'phone-code-1');

        self::assertSame('+8613800138000', $info['phoneNumber']);
        self::assertSame('13800138000', $info['purePhoneNumber']);
        self::assertSame(['code' => 'phone-code-1'], $capture->data);
    }

    public function testUnionPhoneByCodePassesOpenId(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        $union->phoneByCode(Channel::WechatMini, 'phone-code-1', 'OPENID_1');

        self::assertSame(['code' => 'phone-code-1', 'openid' => 'OPENID_1'], $capture->data);
    }

    public function testUnsupportedChannelThrows(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持 code 换取手机号');

        $union->phoneByCode(Channel::BaiduMini, 'phone-code-1');
    }

    public function testUnionPhoneByCodeDouyinMini(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        // 抖音接口返回 RSA 密文，SDK 用 app_private_key 解密后交付明文数组
        $info = $union->phoneByCode(Channel::DouyinMini, 'phone-code-2');

        self::assertSame('+8613900139000', $info['phoneNumber']);
        self::assertSame('13900139000', $info['purePhoneNumber']);
        self::assertStringContainsString('get_phonenumber_info', (string) $capture->uri);
    }

    public function testUnionPhoneByCodeDouyinIgnoresOpenId(): void
    {
        $capture = new \stdClass();
        $union   = $this->buildKernel($capture)->union();

        // 抖音接口无 openid 参数，传入也不应带进请求体
        $union->phoneByCode(Channel::DouyinMini, 'phone-code-2', 'OPENID_1');

        self::assertSame(['code' => 'phone-code-2'], $capture->data);
    }
}
