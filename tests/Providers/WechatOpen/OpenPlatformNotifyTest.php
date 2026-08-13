<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Providers\WechatOpen;

use Kode\MiniApp\Kernel;
use Kode\MiniApp\Providers\WechatOpen\Events\OpenPlatformEvent;
use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;
use Kode\MiniApp\Tests\TestCase;

/**
 * 微信开放平台回调统一入口测试
 */
final class OpenPlatformNotifyTest extends TestCase
{
    private function app(): WechatOpenApp
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app = $kernel->wechatOpen()->app();

        return $app;
    }

    /**
     * 用 Crypto 加密明文并构造回调 body
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function encrypt(WechatOpenApp $app, string $plain, string $timestamp, string $nonce): array
    {
        $json    = $app->crypto()->encryptMessage($plain, $timestamp, $nonce);
        $payload = json_decode($json, true);
        \assert(is_array($payload));

        $body = '<xml><Encrypt><![CDATA[' . $payload['Encrypt'] . ']]></Encrypt></xml>';

        return [$body, (string) $payload['MsgSignature'], $timestamp, $nonce];
    }

    public function testComponentVerifyTicket(): void
    {
        $app   = $this->app();
        $plain = '<xml>'
            . '<AppId><![CDATA[wxcomp123]]></AppId>'
            . '<CreateTime>1413192605</CreateTime>'
            . '<InfoType><![CDATA[component_verify_ticket]]></InfoType>'
            . '<ComponentVerifyTicket><![CDATA[ticket_value_xyz]]></ComponentVerifyTicket>'
            . '</xml>';

        [$body, $sig, $ts, $nonce] = $this->encrypt($app, $plain, '1700000000', 'nonce123');

        $event = $app->notify($body, [
            'msg_signature' => $sig,
            'timestamp'     => $ts,
            'nonce'         => $nonce,
        ]);

        self::assertInstanceOf(OpenPlatformEvent::class, $event);
        self::assertSame('component_verify_ticket', $event->infoType());
        self::assertSame('ticket_value_xyz', $event->ticket());
        self::assertSame('wxcomp123', $event->authorizerAppId());
        self::assertNull($event->authorizationCode());
        self::assertNull($event->event());
    }

    public function testAuthorizedEvent(): void
    {
        $app   = $this->app();
        $plain = '<xml>'
            . '<AppId><![CDATA[wxcomp123]]></AppId>'
            . '<CreateTime>1413192605</CreateTime>'
            . '<InfoType><![CDATA[authorized]]></InfoType>'
            . '<AuthorizerAppid><![CDATA[wxauth123]]></AuthorizerAppid>'
            . '<AuthorizationCode><![CDATA[auth_code_abc]]></AuthorizationCode>'
            . '<AuthorizationCodeExpiredTime>1413196205</AuthorizationCodeExpiredTime>'
            . '</xml>';

        [$body, $sig, $ts, $nonce] = $this->encrypt($app, $plain, '1700000001', 'nonce456');

        $event = $app->notify($body, [
            'msg_signature' => $sig,
            'timestamp'     => $ts,
            'nonce'         => $nonce,
        ]);

        self::assertSame('authorized', $event->infoType());
        self::assertSame('wxauth123', $event->authorizerAppId());
        self::assertSame('auth_code_abc', $event->authorizationCode());
        self::assertSame(1413196205, $event->authorizationCodeExpiredAt());
    }

    public function testPlatformHandleEventForwards(): void
    {
        $kernel = new Kernel([
            'wechat_open' => [
                'component_appid'  => 'wxcomp123',
                'component_secret' => 'comp-secret',
                'token'            => 'verify-token',
                'encoding_aes_key' => str_repeat('a', 43),
            ],
        ]);

        /** @var WechatOpenApp $app */
        $app   = $kernel->wechatOpen()->app();
        $plain = '<xml>'
            . '<AppId><![CDATA[wxcomp123]]></AppId>'
            . '<InfoType><![CDATA[unauthorized]]></InfoType>'
            . '<AuthorizerAppid><![CDATA[wxauth999]]></AuthorizerAppid>'
            . '</xml>';

        [$body, $sig, $ts, $nonce] = $this->encrypt($app, $plain, '1700000002', 'nonce789');

        $event = $kernel->wechatOpen()->handleEvent($body, [
            'msg_signature' => $sig,
            'timestamp'     => $ts,
            'nonce'         => $nonce,
        ]);

        self::assertSame('unauthorized', $event->infoType());
        self::assertSame('wxauth999', $event->authorizerAppId());
    }

    public function testMissingEncryptThrows(): void
    {
        $app = $this->app();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('缺少 Encrypt 节点');

        $app->notify('<xml><foo>bar</foo></xml>', []);
    }
}
