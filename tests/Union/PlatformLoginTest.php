<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 全平台登录端到端集成测试（支付宝 / 抖音 / QQ / 百度 / 企业微信 / 钉钉 / 飞书）。
 *
 * 通过按 URI / 方法 / 参数路由的 Mock HttpClient 模拟各平台真实端点，
 * 验证 v1.16.0 引入的「真实对接、统一校验」在微信之外的渠道同样生效：
 *  - 各渠道登录正确提取 openid 与 unionid（支付宝无 unionid）
 *  - 支付宝登录后拉取用户资料并归一化昵称 / 头像
 *  - 无效 code / 过期授权在各平台均真实抛出 ApiException（杜绝静默失败）
 */
class PlatformLoginTest extends TestCase
{
    private function http(): HttpClientInterface
    {
        return new class implements HttpClientInterface {
            public function get(string $uri, array $options = []): ResponseInterface
            {
                $query = $options['query'] ?? [];
                if (str_contains($uri, '?')) {
                    parse_str((string) parse_url($uri, PHP_URL_QUERY), $embedded);
                    $query = array_merge($embedded, $query);
                }
                return $this->routeGet($uri, $query);
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                $form = $options['form_params'] ?? [];
                if (str_contains($uri, 'gateway.do')) {
                    return $this->routeAlipay(
                        (string) ($form['method'] ?? ''),
                        (string) ($form['code'] ?? '')
                    );
                }
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
                if (str_contains($uri, 'getuserinfo')) {
                    return $this->dingtalkUser((string) ($data['code'] ?? ''));
                }
                if (str_contains($uri, 'tenant_access_token')) {
                    return $this->respond([
                        'code' => 0, 'msg' => 'ok',
                        'tenant_access_token' => 'LK_TENANT', 'expire' => 7200,
                    ]);
                }
                if (str_contains($uri, 'authen/v1/access_token')) {
                    return $this->larkUser((string) ($data['code'] ?? ''));
                }
                return $this->respond([]);
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return $this->respond([]);
            }

            /**
             * @param array<string, mixed> $query
             */
            private function routeGet(string $uri, array $query): ResponseInterface
            {
                if (str_contains($uri, 'jscode2session') && str_contains($uri, 'developer.toutiao.com')) {
                    return $this->douyin((string) ($query['code'] ?? ''));
                }
                if (str_contains($uri, 'sns/jscode2session')) {
                    return $this->qq((string) ($query['js_code'] ?? ''));
                }
                if (str_contains($uri, 'oauth/2.0/token') && str_contains($uri, 'openapi.baidu.com')) {
                    return $this->baidu((string) ($query['code'] ?? ''));
                }
                if (str_contains($uri, 'gettoken') && str_contains($uri, 'qyapi.weixin.qq.com')) {
                    return $this->respond(['access_token' => 'WW_TOK', 'expires_in' => 7200]);
                }
                if (str_contains($uri, 'user/getuserinfo') && str_contains($uri, 'qyapi.weixin.qq.com')) {
                    return $this->wechatWork((string) ($query['code'] ?? ''));
                }
                if (str_contains($uri, 'gettoken') && str_contains($uri, 'oapi.dingtalk.com')) {
                    return $this->respond(['errcode' => 0, 'access_token' => 'DT_TOK', 'expires_in' => 7200]);
                }
                return $this->respond([]);
            }

            private function routeAlipay(string $method, string $code): ResponseInterface
            {
                if ($method === 'alipay.system.oauth.token') {
                    if ($code === 'bad_code') {
                        return $this->respond([
                            'error_response' => [
                                'code' => '40002', 'msg' => '无效',
                                'sub_code' => 'isv.code-invalid', 'sub_msg' => '无效的auth_code',
                            ],
                        ]);
                    }
                    return $this->respond([
                        'alipay_system_oauth_token_response' => [
                            'code' => '10000', 'access_token' => 'ALI_TOK',
                            'user_id' => 'ALI_USER', 'open_id' => 'ALI_OPENID',
                        ],
                        'sign' => 'x',
                    ]);
                }
                if ($method === 'alipay.user.info.share') {
                    return $this->respond([
                        'alipay_user_info_share_response' => [
                            'code' => '10000', 'user_id' => 'ALI_USER',
                            'nick_name' => '支付宝小明', 'avatar' => 'http://ali/avatar.png',
                        ],
                        'sign' => 'x',
                    ]);
                }
                return $this->respond([]);
            }

            private function douyin(string $code): ResponseInterface
            {
                if ($code === 'bad_code') {
                    return $this->respond(['err_no' => 1, 'err_tips' => 'invalid code']);
                }
                return $this->respond([
                    'err_no' => 0, 'err_tips' => '',
                    'data' => [
                        'openid' => 'DY_OPENID', 'unionid' => 'DY_UNION',
                        'session_key' => 'SK', 'anonymous_openid' => 'ANO',
                    ],
                ]);
            }

            private function qq(string $code): ResponseInterface
            {
                if ($code === 'bad_code') {
                    return $this->respond(['errcode' => 40029, 'errmsg' => 'invalid code']);
                }
                return $this->respond(['session_key' => 'SK', 'openid' => 'QQ_OPENID', 'unionid' => 'QQ_UNION']);
            }

            private function baidu(string $code): ResponseInterface
            {
                if ($code === 'bad_code') {
                    return $this->respond(['error' => 'invalid_grant', 'error_description' => 'invalid code']);
                }
                return $this->respond([
                    'access_token' => 'BD_TOK', 'open_id' => 'BD_OPENID',
                    'unionid' => 'BD_UNION', 'expires_in' => 2592000, 'session_key' => 'SK',
                ]);
            }

            private function wechatWork(string $code): ResponseInterface
            {
                if ($code === 'bad_code') {
                    return $this->respond(['errcode' => 40029, 'errmsg' => 'invalid code']);
                }
                return $this->respond(['userid' => 'WW_USERID', 'openid' => 'WW_OPENID', 'unionid' => 'WW_UNION']);
            }

            private function dingtalkUser(string $code): ResponseInterface
            {
                if ($code === 'bad_code') {
                    return $this->respond(['errcode' => 40078, 'errmsg' => 'invalid code']);
                }
                return $this->respond([
                    'errcode' => 0, 'errmsg' => 'ok',
                    'userid' => 'DT_USERID', 'openid' => 'DT_OPENID', 'unionid' => 'DT_UNION',
                ]);
            }

            private function larkUser(string $code): ResponseInterface
            {
                if ($code === 'bad_code') {
                    return $this->respond(['code' => 99991663, 'msg' => 'invalid code']);
                }
                return $this->respond([
                    'code' => 0, 'msg' => 'ok',
                    'data' => [
                        'access_token' => 'LK_TOK', 'open_id' => 'LK_OPENID',
                        'union_id' => 'LK_UNION', 'user_id' => 'LK_USERID', 'expires_in' => 7200,
                    ],
                ]);
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
    }

    private function kernel(): Kernel
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'bits' => 2048]);
        if ($key === false) {
            self::fail('openssl_pkey_new 生成测试私钥失败');
        }
        openssl_pkey_export($key, $privateKey);

        return new Kernel(
            [
                'alipay' => [
                    'app_id'      => 'ali_app',
                    'secret'      => 's3cr3t',
                    'private_key' => $privateKey,
                ],
                'douyin' => [
                    'app_id' => 'tt123',
                    'secret' => 's3cr3t',
                ],
                'qq' => [
                    'app_id' => 'qq_app',
                    'secret' => 's3cr3t',
                ],
                'baidu' => [
                    'app_id' => 'bd_app',
                    'secret' => 's3cr3t',
                ],
                'wechat_work' => [
                    'corp_id'  => 'ww_corp',
                    'secret'   => 's3cr3t',
                    'agent_id' => '1000002',
                    'cache'    => new ArrayCache(),
                ],
                'dingtalk' => [
                    'app_key'    => 'dt_key',
                    'app_secret' => 's3cr3t',
                    'cache'      => new ArrayCache(),
                ],
                'lark' => [
                    'app_id' => 'lk_app',
                    'secret' => 's3cr3t',
                    'cache'  => new ArrayCache(),
                ],
            ],
            $this->http(),
        );
    }

    // ===== 支付宝 =====

    public function testAlipayLoginReturnsOpenidAndProfile(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::AlipayMini, ['code' => 'AUTH_CODE']);

        self::assertSame('ALI_USER', $user->openId);
        self::assertSame('', $user->unionId, '支付宝无 unionid 概念');
        self::assertSame(Channel::AlipayMini, $user->channel);
        self::assertSame('支付宝小明', $user->nickname, '支付宝 nick_name 应归一化为 nickname');
        self::assertSame('http://ali/avatar.png', $user->avatar);
    }

    public function testAlipayLoginInvalidCodeThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->authenticate(Channel::AlipayMini, ['code' => 'bad_code']);
    }

    // ===== 抖音 =====

    public function testDouyinLoginReturnsOpenidAndUnionId(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::DouyinMini, ['code' => 'JS_CODE']);

        self::assertSame('DY_OPENID', $user->openId);
        self::assertSame('DY_UNION', $user->unionId);
        self::assertSame(Channel::DouyinMini, $user->channel);
    }

    public function testDouyinLoginInvalidCodeThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->authenticate(Channel::DouyinMini, ['code' => 'bad_code']);
    }

    // ===== QQ =====

    public function testQqLoginReturnsOpenidAndUnionId(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::Qq, ['code' => 'JS_CODE']);

        self::assertSame('QQ_OPENID', $user->openId);
        self::assertSame('QQ_UNION', $user->unionId);
        self::assertSame(Channel::Qq, $user->channel);
    }

    public function testQqLoginInvalidCodeThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->authenticate(Channel::Qq, ['code' => 'bad_code']);
    }

    // ===== 百度 =====

    public function testBaiduLoginReturnsOpenidAndUnionId(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::BaiduMini, ['code' => 'JS_CODE']);

        self::assertSame('BD_OPENID', $user->openId);
        self::assertSame('BD_UNION', $user->unionId);
        self::assertSame(Channel::BaiduMini, $user->channel);
    }

    public function testBaiduLoginInvalidCodeThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->authenticate(Channel::BaiduMini, ['code' => 'bad_code']);
    }

    // ===== 企业微信 =====

    public function testWechatWorkLoginReturnsOpenidAndUnionId(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::WechatWork, ['code' => 'WW_CODE']);

        self::assertSame('WW_OPENID', $user->openId);
        self::assertSame('WW_UNION', $user->unionId);
        self::assertSame(Channel::WechatWork, $user->channel);
    }

    public function testWechatWorkLoginInvalidCodeThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->authenticate(Channel::WechatWork, ['code' => 'bad_code']);
    }

    // ===== 钉钉 =====

    public function testDingtalkLoginReturnsOpenidAndUnionId(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::Dingtalk, ['code' => 'DT_CODE']);

        self::assertSame('DT_OPENID', $user->openId);
        self::assertSame('DT_UNION', $user->unionId);
        self::assertSame(Channel::Dingtalk, $user->channel);
    }

    public function testDingtalkLoginInvalidCodeThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->authenticate(Channel::Dingtalk, ['code' => 'bad_code']);
    }

    // ===== 飞书 =====

    public function testLarkLoginReturnsOpenidAndUnionId(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::Lark, ['code' => 'LK_CODE']);

        self::assertSame('LK_OPENID', $user->openId);
        self::assertSame('LK_UNION', $user->unionId);
        self::assertSame(Channel::Lark, $user->channel);
    }

    public function testLarkLoginInvalidCodeThrows(): void
    {
        $this->expectException(ApiException::class);
        $this->kernel()->union()->authenticate(Channel::Lark, ['code' => 'bad_code']);
    }
}
