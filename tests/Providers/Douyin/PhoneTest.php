<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Douyin;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\Douyin\DouyinApp;
use Kode\MiniApp\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 抖音小程序 code 换取手机号集成测试
 *
 * 现场生成 RSA 密钥对，用公钥按抖音服务端一致的方式（PKCS#1 v1.5 + base64）
 * 构造密文，覆盖 client_token 获取、RSA 解密、水印校验与各类失败语义。
 */
class PhoneTest extends TestCase
{
    private const string APP_ID = 'tt1234567890';

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
     * 模拟抖音服务端：用应用公钥加密明文并 base64
     *
     * @param array<string, mixed> $payload
     */
    private function encrypt(array $payload): string
    {
        $plain  = (string) json_encode($payload);
        $cipher = '';

        foreach (str_split($plain, 245) as $chunk) {
            $block = '';
            self::assertTrue(openssl_public_encrypt($chunk, $block, $this->publicKey, OPENSSL_PKCS1_PADDING));
            $cipher .= $block;
        }

        return base64_encode($cipher);
    }

    /**
     * 手机号明文（含 watermark）
     *
     * @return array<string, mixed>
     */
    private function phonePayload(string $appId = self::APP_ID): array
    {
        return [
            'phoneNumber'     => '+8613800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
            'watermark'       => ['appid' => $appId, 'timestamp' => 1637744274],
        ];
    }

    /**
     * 构建带桩的 App：postJson 按 URL 路由 client_token 与 get_phonenumber_info
     *
     * @param array<string, mixed> $phoneBody get_phonenumber_info 的响应体
     */
    private function buildApp(
        array $phoneBody,
        \stdClass $capture,
        bool $withPrivateKey = true,
        ?ArrayCache $cache = null,
    ): DouyinApp {
        $capture->tokenCalls = 0;

        $stub = new class ($phoneBody, $capture) implements HttpClientInterface {
            /**
             * @param array<string, mixed> $phoneBody
             */
            public function __construct(
                private array $phoneBody,
                private \stdClass $capture,
            ) {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                return $this->respond([]);
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
                    $this->capture->tokenUri  = $uri;
                    $this->capture->tokenData = $data;
                    ++$this->capture->tokenCalls;

                    return $this->respond([
                        'data' => [
                            'access_token' => 'clt.CLIENT_TOKEN_1',
                            'expires_in'   => 7200,
                            'error_code'   => 0,
                            'description'  => '',
                        ],
                        'message' => 'success',
                    ]);
                }

                $this->capture->uri     = $uri;
                $this->capture->data    = $data;
                $this->capture->headers = $headers;

                return $this->respond($this->phoneBody);
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

        $config = [
            'app_id'     => self::APP_ID,
            'app_secret' => 'app-secret',
            'cache'      => $cache ?? new ArrayCache(),
        ];
        if ($withPrivateKey) {
            $config['app_private_key'] = $this->privateKey;
        }

        $app = (new Kernel(['douyin' => $config], $stub))->douyin()->app();
        \assert($app instanceof DouyinApp);

        return $app;
    }

    /**
     * 成功响应体
     *
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function successBody(?array $payload = null): array
    {
        return [
            'data'     => $this->encrypt($payload ?? $this->phonePayload()),
            'err_no'   => 0,
            'err_tips' => 'success',
        ];
    }

    public function testByCodeReturnsDecryptedPhoneInfo(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $info = $app->phone()->byCode('phone-code-1');

        self::assertSame('+8613800138000', $info['phoneNumber']);
        self::assertSame('13800138000', $info['purePhoneNumber']);
        self::assertSame('86', $info['countryCode']);
        self::assertArrayHasKey('watermark', $info);
    }

    public function testByCodeHitsOfficialEndpointWithClientToken(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $app->phone()->byCode('phone-code-1');

        self::assertSame(
            'https://open.douyin.com/api/apps/v1/get_phonenumber_info/',
            (string) $capture->uri,
        );
        self::assertSame(['code' => 'phone-code-1'], $capture->data);
        self::assertSame(['access-token' => 'clt.CLIENT_TOKEN_1'], $capture->headers);
    }

    public function testClientTokenUsesClientCredentialGrant(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $app->phone()->byCode('phone-code-1');

        self::assertSame('https://open.douyin.com/oauth/client_token/', (string) $capture->tokenUri);
        self::assertSame([
            'grant_type'    => 'client_credential',
            'client_key'    => self::APP_ID,
            'client_secret' => 'app-secret',
        ], $capture->tokenData);
    }

    public function testClientTokenIsCachedAcrossCalls(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $app->phone()->byCode('phone-code-1');
        $app->phone()->byCode('phone-code-2');

        self::assertSame(1, $capture->tokenCalls, 'client_token 应命中缓存，只请求一次');
    }

    public function testForgetClientTokenForcesRefetch(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $app->phone()->byCode('phone-code-1');
        $app->phone()->forgetClientToken();
        $app->phone()->byCode('phone-code-2');

        self::assertSame(2, $capture->tokenCalls, '清缓存后应重新获取 client_token');
    }

    public function testNumberByCodeReturnsPlainString(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        self::assertSame('+8613800138000', $app->phone()->numberByCode('phone-code-1'));
    }

    public function testPureNumberByCodeReturnsPlainString(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        self::assertSame('13800138000', $app->phone()->pureNumberByCode('phone-code-1'));
    }

    public function testByCodeRejectsEmptyCode(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('code 不能为空');

        $app->phone()->byCode('   ');
    }

    public function testByCodeRequiresPrivateKey(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody(), $capture, withPrivateKey: false);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('未配置 app_private_key');

        $app->phone()->byCode('phone-code-1');
    }

    public function testByCodeThrowsOnApiError(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp([
            'data'     => '',
            'err_no'   => 28005187,
            'err_tips' => 'code 已过期',
        ], $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('code 已过期');

        $app->phone()->byCode('expired-code');
    }

    public function testByCodeThrowsOnEmptyCipher(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp(['data' => '', 'err_no' => 0, 'err_tips' => 'success'], $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('响应缺少密文数据');

        $app->phone()->byCode('phone-code-1');
    }

    public function testByCodeRejectsForeignWatermark(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody($this->phonePayload('tt-other-app')), $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('watermark.appid 校验不通过');

        $app->phone()->byCode('phone-code-1');
    }

    public function testByCodeRejectsMissingWatermark(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody([
            'phoneNumber'     => '+8613800138000',
            'purePhoneNumber' => '13800138000',
            'countryCode'     => '86',
        ]), $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('缺少 watermark 节点');

        $app->phone()->byCode('phone-code-1');
    }

    public function testByCodeRejectsMissingPhoneField(): void
    {
        $capture = new \stdClass();
        $app     = $this->buildApp($this->successBody([
            'phoneNumber' => '+8613800138000',
            'countryCode' => '86',
            'watermark'   => ['appid' => self::APP_ID, 'timestamp' => 1637744274],
        ]), $capture);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('purePhoneNumber');

        $app->phone()->byCode('phone-code-1');
    }

    public function testByCodeThrowsWhenPrivateKeyMismatched(): void
    {
        $other = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($other);
        openssl_pkey_export($other, $otherKey);

        $body    = $this->successBody();
        $capture = new \stdClass();

        // 用另一对密钥的私钥去解本应用公钥加密的密文
        $this->privateKey = (string) $otherKey;
        $app              = $this->buildApp($body, $capture);

        // OpenSSL 3.x 对 PKCS#1 v1.5 启用隐式拒绝（Marvin 攻击缓解）：私钥不匹配时
        // 不一定报解密错误，也可能返回随机明文进而 JSON 解析失败，故只断言异常类型。
        $this->expectException(ApiException::class);

        $app->phone()->byCode('phone-code-1');
    }
}
