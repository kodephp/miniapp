<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\Wechat;

use Kode\MiniApp\Contracts\ChannelFeature;
use Kode\MiniApp\Exceptions\ConfigException;
use Kode\MiniApp\Providers\Wechat\WechatConfig;
use PHPUnit\Framework\TestCase;

/**
 * 微信配置契约测试
 *
 * 验证 requiredKeys() / requiredKeysFor(Pay) / validate() / validateFeature()
 * 在缺键时抛出清晰异常并列出缺失项。
 */
final class WechatConfigContractTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    private function config(array $data): WechatConfig
    {
        return new WechatConfig($data);
    }

    public function testRequiredKeysAreAppId(): void
    {
        $config = $this->config(['app_id' => 'wx']);
        self::assertSame(['app_id'], $config->requiredKeys());
    }

    public function testPayRequiresMchIdKeyPathSerialNo(): void
    {
        $config = $this->config(['app_id' => 'wx']);
        self::assertSame(
            ['mch_id', 'key_path', 'mch_serial_no'],
            $config->requiredKeysFor(ChannelFeature::Pay),
        );
    }

    public function testValidatePassesWhenAppIdPresent(): void
    {
        $config = $this->config(['app_id' => 'wx']);
        $config->validate();
        self::assertSame(['app_id'], $config->requiredKeys());
    }

    public function testValidateThrowsOnMissingAppId(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('app_id');
        $this->config([])->validate();
    }

    public function testValidateFeaturePayListsMissingKeys(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('mch_id');
        $this->config(['app_id' => 'wx'])->validateFeature(ChannelFeature::Pay);
    }

    public function testValidateFeaturePayPassesWithAllKeys(): void
    {
        $config = $this->config([
            'app_id'        => 'wx',
            'mch_id'        => 'mch',
            'key_path'      => '/tmp/k.pem',
            'mch_serial_no' => 'serial',
        ]);
        $config->validateFeature(ChannelFeature::Pay);
        self::assertSame(
            ['mch_id', 'key_path', 'mch_serial_no'],
            $config->requiredKeysFor(ChannelFeature::Pay),
        );
    }
}
