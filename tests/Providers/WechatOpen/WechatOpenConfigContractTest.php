<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatOpen;

use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Exceptions\ConfigException;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenConfig;
use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;

/**
 * 微信开放平台配置契约测试
 *
 * 覆盖 requiredKeys / requiredKeysFor / validate，并验证别名兼容与
 * Union::capabilities 聚合必填配置。
 */
final class WechatOpenConfigContractTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    private function config(array $data): WechatOpenConfig
    {
        $kernel = new Kernel(['wechat_open' => $data]);

        /** @var WechatOpenConfig $config */
        $config = $kernel->wechatOpen()->config();

        return $config;
    }

    public function testRequiredKeys(): void
    {
        $config = $this->config([
            'component_appid'  => 'wxcomp',
            'component_secret' => 'sec',
            'token'            => 'tok',
            'encoding_aes_key' => str_repeat('a', 43),
        ]);

        self::assertSame(
            ['component_appid', 'component_secret', 'token', 'encoding_aes_key'],
            $config->requiredKeys(),
        );
    }

    public function testRequiredKeysForFeature(): void
    {
        $config = $this->config([
            'component_appid'  => 'wxcomp',
            'component_secret' => 'sec',
            'token'            => 'tok',
            'encoding_aes_key' => str_repeat('a', 43),
        ]);

        self::assertSame($config->requiredKeys(), $config->requiredKeysFor(ChannelFeature::Login));
        self::assertSame($config->requiredKeys(), $config->requiredKeysFor(ChannelFeature::User));
        self::assertSame(['token', 'encoding_aes_key'], $config->requiredKeysFor(ChannelFeature::Notify));
        self::assertSame([], $config->requiredKeysFor(ChannelFeature::Pay));
    }

    public function testValidatePassesWithCanonicalKeys(): void
    {
        $config = $this->config([
            'component_appid'  => 'wxcomp',
            'component_secret' => 'sec',
            'token'            => 'tok',
            'encoding_aes_key' => str_repeat('a', 43),
        ]);

        $config->validate();
        $config->validateFeature(ChannelFeature::Login);
        $config->validateFeature(ChannelFeature::Notify);

        self::assertSame('wxcomp', $config->componentAppId());
    }

    public function testValidatePassesWithAliases(): void
    {
        // 开放平台配置兼容别名：app_id / secret / aes_key
        $config = $this->config([
            'app_id'  => 'wxcomp',
            'secret'  => 'sec',
            'token'   => 'tok',
            'aes_key' => str_repeat('a', 43),
        ]);

        $config->validate();
        $config->validateFeature(ChannelFeature::Login);

        self::assertSame('wxcomp', $config->componentAppId());
    }

    public function testValidateThrowsWhenMissingComponentSecret(): void
    {
        $config = $this->config([
            'component_appid'  => 'wxcomp',
            'token'            => 'tok',
            'encoding_aes_key' => str_repeat('a', 43),
        ]);

        $this->expectException(ConfigException::class);
        $config->validate();
    }

    public function testValidateFeatureNotifyThrowsWhenMissingAesKey(): void
    {
        $config = $this->config([
            'component_appid'  => 'wxcomp',
            'component_secret' => 'sec',
            'token'            => 'tok',
        ]);

        $this->expectException(ConfigException::class);
        $config->validateFeature(ChannelFeature::Notify);
    }

    public function testValidateFeatureNotifyPassesWithoutComponentAppId(): void
    {
        // Notify 只需 token + aes_key，不依赖 component_appid
        $config = $this->config([
            'token'            => 'tok',
            'encoding_aes_key' => str_repeat('a', 43),
        ]);

        $config->validateFeature(ChannelFeature::Notify);

        self::assertSame('tok', $config->token());
    }

    public function testCapabilitiesAggregatesRequiredConfig(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp',
                'component_secret' => 'sec',
                'token'            => 'tok',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var Union $union */
        $union = $kernel->union();
        $info  = $union->capabilities(Channel::WechatOpen);

        self::assertTrue($info->supports(ChannelFeature::Login));
        self::assertTrue($info->supports(ChannelFeature::Notify));
        self::assertContains('component_appid', $info->requiredConfig);
        self::assertContains('encoding_aes_key', $info->requiredConfig);
    }
}
