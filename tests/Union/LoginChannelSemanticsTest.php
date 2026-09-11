<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Contracts\HttpClientInterface;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Channels\Alipay\AlipayLoginAdapter;
use Kode\MiniApp\Union\Channels\Douyin\DouyinLoginAdapter;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\Union;
use Kode\MiniApp\Union\UnionUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * 登录适配器通道语义回归（doc：MINIAPP_PATCH_WECHAT_LOGIN §2.3 串味 BUG 修复）
 *
 * 核心契约：authenticate(Channel::X) 产出的 UnionUser->channel 必须等于 X（零串味），
 * 且 registerLoginAdapter 以 channel()->value 为槽位键，共用适配器必须按目标通道区分槽位。
 */
final class LoginChannelSemanticsTest extends TestCase
{
    public function testAuthenticateWechatH5ReturnsH5Channel(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::WechatH5, ['code' => 'H5_CODE']);

        self::assertInstanceOf(UnionUser::class, $user);
        self::assertSame(Channel::WechatH5, $user->channel, '微信内 H5 认证后通道不得串味为 wechat_mp');
        // H5 与公众号共用同一公众号凭据（doc §4：H5 靠服务号兜底），openid 一致
        self::assertSame('OPENID_MP', $user->openId);
        self::assertSame('UNION_001', $user->unionId);
    }

    public function testAuthenticateWechatMpReturnsMpChannel(): void
    {
        $user = $this->kernel()->union()->authenticate(Channel::WechatMp, ['code' => 'MP_CODE']);

        self::assertInstanceOf(UnionUser::class, $user);
        self::assertSame(Channel::WechatMp, $user->channel);
    }

    public function testRegisterCustomH5AdapterDoesNotClobberMpSlot(): void
    {
        $kernel = $this->kernel();
        $union  = $kernel->union();

        // 自定义 H5 适配器（独立实现，验证槽位不再与 wechat_mp 冲突）
        $custom = new class implements LoginAdapter {
            public function channel(): Channel
            {
                return Channel::WechatH5;
            }

            public function authenticate(array $payload): UnionUser
            {
                return UnionUser::fromRaw(
                    channel: Channel::WechatH5,
                    openId:  'CUSTOM_H5_OPENID',
                    unionId: '',
                    raw:     [],
                    extra:   [],
                );
            }
        };
        $union->registerLoginAdapter($custom);

        // wechat_h5 槽位走自定义实现
        $viaCustom = $union->authenticate(Channel::WechatH5, ['code' => 'ANY']);
        self::assertSame('CUSTOM_H5_OPENID', $viaCustom->openId);

        // wechat_mp 槽位仍走内置 MpLoginAdapter（未被覆盖）
        $viaBuiltin = $union->authenticate(Channel::WechatMp, ['code' => 'MP_CODE']);
        self::assertSame('OPENID_MP', $viaBuiltin->openId);
        self::assertSame(Channel::WechatMp, $viaBuiltin->channel);
    }

    /**
     * 共用适配器构造时注入目标通道后，channel() 必须返回注入值（而非硬编码默认）。
     *
     * @param callable(Kernel): LoginAdapter $factory
     * @param Channel                        $expected
     */
    #[DataProvider('targetChannelProvider')]
    public function testSharedAdapterHonorsInjectedTargetChannel(callable $factory, Channel $expected): void
    {
        $kernel = new Kernel([], new FakeHttpClient());
        $adapter = $factory($kernel);

        self::assertSame($expected, $adapter->channel());
    }

    public static function targetChannelProvider(): \Generator
    {
        yield 'alipay_mp' => [
            static fn (Kernel $k): LoginAdapter => new AlipayLoginAdapter($k, Channel::AlipayMp),
            Channel::AlipayMp,
        ];
        yield 'alipay_app' => [
            static fn (Kernel $k): LoginAdapter => new AlipayLoginAdapter($k, Channel::AlipayApp),
            Channel::AlipayApp,
        ];
        yield 'alipay_mini_default' => [
            static fn (Kernel $k): LoginAdapter => new AlipayLoginAdapter($k),
            Channel::AlipayMini,
        ];
        yield 'douyin_mp' => [
            static fn (Kernel $k): LoginAdapter => new DouyinLoginAdapter($k, Channel::DouyinMp),
            Channel::DouyinMp,
        ];
        yield 'douyin_mini_default' => [
            static fn (Kernel $k): LoginAdapter => new DouyinLoginAdapter($k),
            Channel::DouyinMini,
        ];
    }

    /**
     * 构造带 sns/oauth2/access_token 桩的 Kernel（公众号 OAuth 换票链路）
     */
    private function kernel(): Kernel
    {
        $routes = [
            'sns/oauth2/access_token' => [
                'access_token' => 'MP_OAUTH_TOK',
                'openid'       => 'OPENID_MP',
                'unionid'      => 'UNION_001',
                'expires_in'   => 7200,
            ],
            'sns/userinfo' => [
                'openid'     => 'OPENID_MP',
                'nickname'   => '公众号小明',
                'unionid'    => 'UNION_001',
            ],
        ];

        return new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx123',
                    'secret' => 's3cr3t',
                    'cache'  => new ArrayCache(),
                ],
            ],
            self::routeHttp($routes),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $routes
     */
    private static function routeHttp(array $routes): HttpClientInterface
    {
        return new class ($routes) implements HttpClientInterface {
            /**
             * @param array<string, array<string, mixed>> $routes
             */
            public function __construct(private array $routes)
            {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                foreach ($this->routes as $needle => $body) {
                    if (str_contains($uri, $needle)) {
                        return self::respond($body);
                    }
                }

                return self::respond([]);
            }

            public function post(string $uri, array $options = []): ResponseInterface
            {
                return self::respond([]);
            }

            public function put(string $uri, array $options = []): ResponseInterface
            {
                return self::respond([]);
            }

            public function patch(string $uri, array $options = []): ResponseInterface
            {
                return self::respond([]);
            }

            public function delete(string $uri, array $options = []): ResponseInterface
            {
                return self::respond([]);
            }

            public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface
            {
                return self::respond([]);
            }

            public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface
            {
                return self::respond([]);
            }

            /**
             * @param array<string, mixed> $body
             */
            private static function respond(array $body): ResponseInterface
            {
                return new \Kode\MiniApp\Tests\Fakes\FakeResponse($body);
            }
        };
    }
}
