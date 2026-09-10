<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union;

use Kode\MiniApp\Core\ArrayCache;
use Kode\MiniApp\Kernel;
use Kode\MiniApp\Tests\Fakes\FakeHttpClient;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Union;
use PHPUnit\Framework\TestCase;

/**
 * 微信网页授权 / 扫码登录 URL 生成 e2e（oauth2/authorize + connect/qrconnect）
 *
 * 修补背景（v2.0.41）：包此前仅在深层暴露 WechatOpenApp::openApp()->qrConnectUrl()，
 * 公众号网页授权 URL（oauth2/authorize）全缺；业务侧 WechatOauthService 只能自行拼 URL。
 * 本测试锁定 Union 门面 `authorizeUrl()` / `qrConnectUrl()` 的 URL 拼装契约：
 * 端点、参数顺序无关精确断言、#wechat_redirect 锚点、scope 白名单大声失败、
 * 渠道守卫大声失败、site_app_id 回退 app_id。
 */
final class OauthUrlTest extends TestCase
{
    public function testMpAuthorizeUrlBuildsExactWechatRedirectUrl(): void
    {
        $union = $this->union(siteAppId: 'wx_site');

        $url = $union->authorizeUrl(
            Channel::WechatMp,
            'https://biz.example.com/wechat/callback',
            'snsapi_userinfo',
            'csrf-xyz',
        );

        self::assertSame(
            'https://open.weixin.qq.com/connect/oauth2/authorize'
            . '?appid=wx_mp'
            . '&redirect_uri=' . urlencode('https://biz.example.com/wechat/callback')
            . '&response_type=code'
            . '&scope=snsapi_userinfo'
            . '&state=csrf-xyz'
            . '#wechat_redirect',
            $url,
        );
    }

    public function testH5ChannelUsesSameAuthorizeEndpoint(): void
    {
        $union = $this->union(siteAppId: 'wx_site');

        $url = $union->authorizeUrl(Channel::WechatH5, 'https://biz.example.com/h5', 'snsapi_base', 's1');

        self::assertStringStartsWith('https://open.weixin.qq.com/connect/oauth2/authorize?', $url);
        self::assertStringContainsString('appid=wx_mp', $url);
        self::assertStringContainsString('scope=snsapi_base', $url);
        self::assertStringEndsWith('#wechat_redirect', $url);
    }

    public function testAuthorizeUrlDefaultsToSnsapiBaseScope(): void
    {
        $union = $this->union(siteAppId: 'wx_site');

        $url = $union->authorizeUrl(Channel::WechatMp, 'https://biz.example.com/cb');

        self::assertStringContainsString('scope=snsapi_base', $url);
        self::assertStringContainsString('state=state', $url);
    }

    public function testAuthorizeUrlRejectsInvalidScope(): void
    {
        $union = $this->union(siteAppId: 'wx_site');

        // snsapi_login 属于开放平台 qrconnect 端点，不属于公众号网页授权 —— 大声失败
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('scope 仅支持');

        $union->authorizeUrl(Channel::WechatMp, 'https://biz.example.com/cb', 'snsapi_login');
    }

    public function testAuthorizeUrlRejectsUnsupportedChannel(): void
    {
        $union = $this->union(siteAppId: 'wx_site');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持网页授权 URL');

        $union->authorizeUrl(Channel::DouyinMini, 'https://biz.example.com/cb');
    }

    public function testQrConnectUrlBuildsExactScanLoginUrl(): void
    {
        $union = $this->union(siteAppId: 'wx_site');

        $url = $union->qrConnectUrl(Channel::WechatPc, 'https://biz.example.com/pc/callback', 'csrf-pc');

        self::assertSame(
            'https://open.weixin.qq.com/connect/qrconnect'
            . '?appid=wx_site'
            . '&redirect_uri=' . urlencode('https://biz.example.com/pc/callback')
            . '&response_type=code'
            . '&scope=snsapi_login'
            . '&state=csrf-pc'
            . '#wechat_redirect',
            $url,
        );
    }

    public function testQrConnectUrlFallsBackToAppIdWithoutSiteAppId(): void
    {
        $union = $this->union(siteAppId: null);

        $url = $union->qrConnectUrl(Channel::WechatPc, 'https://biz.example.com/pc');

        self::assertStringContainsString('appid=wx_open_app', $url);
    }

    public function testQrConnectUrlRejectsUnsupportedChannel(): void
    {
        $union = $this->union(siteAppId: 'wx_site');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('暂不支持扫码登录 URL');

        $union->qrConnectUrl(Channel::WechatMp, 'https://biz.example.com/cb');
    }

    public function testModuleAuthorizeUrlSupportsAppIdDefaultAndNoRedirectAnchor(): void
    {
        $app = $this->kernel(siteAppId: 'wx_site')->wechat()->app();
        self::assertInstanceOf(\Kode\MiniApp\Providers\Wechat\WechatApp::class, $app);

        // appId 留空 → 从配置读取；wechatRedirect=false → 不追加锚点
        $url = $app->oauth()->authorizeUrl('', 'https://biz.example.com/cb', wechatRedirect: false);

        self::assertStringContainsString('appid=wx_mp', $url);
        self::assertStringEndsNotWith('#wechat_redirect', $url);
    }

    private function union(?string $siteAppId): Union
    {
        return $this->kernel($siteAppId)->union();
    }

    private function kernel(?string $siteAppId): Kernel
    {
        $wechatOpen = [
            'app_id'     => 'wx_open_app',
            'secret'     => 'open-secret',
            'site_app_id' => $siteAppId ?? 'wx_site',
            'site_secret' => 'site-secret',
            'cache'      => new ArrayCache(),
        ];
        if ($siteAppId === null) {
            unset($wechatOpen['site_app_id'], $wechatOpen['site_secret']);
        }

        $kernel = new Kernel(
            [
                'wechat' => [
                    'app_id' => 'wx_mp',
                    'secret' => 'mp-secret',
                    'cache'  => new ArrayCache(),
                ],
                'wechat_open' => $wechatOpen,
            ],
            new FakeHttpClient(),
        );

        return $kernel;
    }
}
