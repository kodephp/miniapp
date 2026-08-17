<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Union\CapabilityInfo;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;

/**
 * 非支付能力矩阵一致性回归
 *
 * 以「预期矩阵（SoT）」断言 {@see Channel::features()} 声明的非支付能力
 * （Login / User / Decrypt / Phone）与真实适配器实现一致，防止能力声明漂移。
 *
 * 漂移示例（修复于 v2.0.38）：飞书 Lark 经 LarkApp::decrypt() 真实支持解密与手机号
 * 解密（PhoneByDecryptTest / DecryptTest 均覆盖 Channel::Lark），但 Channel::features()
 * 曾仅声明 [Login, User]，漏报 Decrypt / Phone。本测试将预期写死，未来任何渠道的能力
 * 声明被误删 / 误加都会在此失败。
 *
 * 与 PaysBridgeCapabilityMatrixConsistencyTest 同构：声明即事实，漂移即红。
 */
final class ChannelFeatureMatrixTest extends TestCase
{
    /**
     * 预期矩阵（SoT）：渠道 => 应支持的非支付能力集合
     *
     * 依据：DecryptTest / UserInfoByDecryptTest / PhoneBy*Test 实测覆盖
     * + 各 Provider App::decrypt() 真实实现。
     *
     * @return array<string, array{0: Channel, 1: array<ChannelFeature>}>
     */
    public static function expectedMatrixProvider(): array
    {
        $id = static fn (ChannelFeature ...$f) => $f;

        return [
            '微信公众'     => [Channel::WechatMp,   $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '微信小程序'   => [Channel::WechatMini, $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '微信 APP'     => [Channel::WechatApp,  $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '微信 H5'      => [Channel::WechatH5,   $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User)],
            '微信 PC'      => [Channel::WechatPc,   $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User)],
            '微信开放平台' => [Channel::WechatOpen, $id(ChannelFeature::Login, ChannelFeature::Notify, ChannelFeature::User)],
            '企业微信'     => [Channel::WechatWork, $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            'QQ'           => [Channel::Qq,         $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '支付宝小程序' => [Channel::AlipayMini, $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '支付宝生活号' => [Channel::AlipayMp,   $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '支付宝 APP'   => [Channel::AlipayApp,  $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '抖音小程序'   => [Channel::DouyinMini, $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '抖音头条号'   => [Channel::DouyinMp,   $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '百度小程序'   => [Channel::BaiduMini,  $id(ChannelFeature::Login, ChannelFeature::Pay, ChannelFeature::Notify, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '钉钉'         => [Channel::Dingtalk,   $id(ChannelFeature::Login, ChannelFeature::User)],
            '飞书'         => [Channel::Lark,       $id(ChannelFeature::Login, ChannelFeature::User, ChannelFeature::Decrypt, ChannelFeature::Phone)],
            '加密货币'     => [Channel::Crypto,     $id(ChannelFeature::Pay)],
        ];
    }

    /**
     * Channel::features() 必须精确等于预期矩阵（顺序无关）。
     *
     * @dataProvider expectedMatrixProvider
     * @param ChannelFeature[] $expected
     */
    public function testChannelFeaturesMatchExpected(Channel $channel, array $expected): void
    {
        $actual = $channel->features();

        $toValues = static fn (array $fs): array => array_map(static fn (ChannelFeature $f) => $f->value, $fs);
        $actualValues   = $toValues($actual);
        $expectedValues = $toValues($expected);
        sort($actualValues);
        sort($expectedValues);

        self::assertSame(
            $expectedValues,
            $actualValues,
            "渠道 [{$channel->label()}] 的能力声明与预期矩阵不一致（声明漂移）",
        );
    }

    /**
     * 飞书 Lark 必须真实声明 Decrypt + Phone（历史漂移修复回归）。
     */
    public function testLarkDeclaresDecryptAndPhone(): void
    {
        self::assertTrue(Channel::Lark->supports(ChannelFeature::Decrypt), '飞书应支持解密（LarkApp::decrypt 已实现且测试覆盖）');
        self::assertTrue(Channel::Lark->supports(ChannelFeature::Phone), '飞书应支持手机号解密');
    }

    /**
     * Union::capabilities() 应把 Channel::features() 如实聚合，
     * 且 Phone 作为非支付能力不引入额外必填配置。
     */
    public function testCapabilitiesAggregatesPhoneWithoutExtraConfig(): void
    {
        $union = $this->union();
        $info  = $union->capabilities(Channel::WechatMini);

        self::assertInstanceOf(CapabilityInfo::class, $info);
        self::assertTrue($info->supports(ChannelFeature::Phone));
        self::assertTrue($info->supports(ChannelFeature::Decrypt));
        // Phone 不引入新必填配置（能力发现仅依赖既有登录配置）
        self::assertNotContains('phone', $info->requiredConfig);
    }

    private function union(): Union
    {
        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id'        => 'wx',
                    'secret'        => 'sec',
                    'mch_id'        => 'mch',
                    'mch_serial_no' => 'serial',
                    'key_path'      => '/tmp/none.pem',
                    'cache'         => new ArrayCache(),
                ],
            ],
            new FakeHttpClient(),
        );

        return $kernel->union();
    }
}
