<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Wechat;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 微信公众号登录适配器（OAuth 网页授权）
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::WechatMp, ['code' => 'xxx']);
 *
 * 内部实现：
 *   1. 通过 code 换取 access_token / openid / unionid
 *   2. 拉取用户基本信息
 *   3. 构造 UnionUser 统一对象
 */
final class MpLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function channel(): Channel
    {
        return Channel::WechatMp;
    }

    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        $provider   = $this->provider('wechat');
        $app        = $provider->app();
        if (!$app instanceof \Kode\MiniApp\Providers\Wechat\WechatApp) {
            throw new \RuntimeException('公众号登录要求 wechat Provider');
        }
        $http       = $app->http();
        $config     = $app->config();
        $appId      = $config->appId();
        $appSecret  = $config->secret();

        // 1. code 换 access_token
        $tokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token'
            . '?appid=' . urlencode($appId)
            . '&secret=' . urlencode($appSecret)
            . '&code=' . urlencode($code)
            . '&grant_type=authorization_code';

        $tokenRaw = $this->parseResponse($http->get($tokenUrl));
        if (isset($tokenRaw['errcode']) && (int) $tokenRaw['errcode'] !== 0) {
            throw new \RuntimeException(
                "公众号 OAuth 换取 access_token 失败: " . self::str($tokenRaw, 'errmsg')
            );
        }

        $accessToken = self::str($tokenRaw, 'access_token');
        $openId      = self::str($tokenRaw, 'openid');
        $unionId     = self::strOrNull($tokenRaw, 'unionid') ?? '';

        // 2. 拉取用户信息（需 scope 为 snsapi_userinfo）
        $raw = [];
        if ($accessToken !== '' && $openId !== '') {
            $userUrl = 'https://api.weixin.qq.com/sns/userinfo'
                . '?access_token=' . urlencode($accessToken)
                . '&openid=' . urlencode($openId)
                . '&lang=zh_CN';
            $raw = $this->parseResponse($http->get($userUrl));
        }

        return UnionUser::fromRaw(
            channel: Channel::WechatMp,
            openId:  $openId,
            unionId: $unionId,
            raw:     $raw,
            extra:   $tokenRaw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(\Psr\Http\Message\ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        return is_array($data) ? $data : [];
    }
}
