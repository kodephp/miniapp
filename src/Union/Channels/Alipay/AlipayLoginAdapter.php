<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Alipay;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Alipay\AlipayApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 支付宝登录适配器（支持小程序 / 生活号 / App 三种场景）
 *
 * 业务流程：
 *   1. 客户端授权登录，拿到 auth_code（小程序）或 code（生活号）
 *   2. 服务端调 alipay.system.oauth.token 换 access_token / user_id
 *   3. 调 alipay.user.info.share 拉用户信息
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::AlipayMini, ['code' => 'xxx']);
 *   $user = $kernel->union()->authenticate(Channel::AlipayMp,   ['code' => 'xxx']);
 *   $user = $kernel->union()->authenticate(Channel::AlipayApp,  ['code' => 'xxx']);
 */
final class AlipayLoginAdapter extends BaseAdapter implements LoginAdapter
{
    public function __construct(
        \Kode\MiniApp\Contracts\KernelInterface $kernel,
        private readonly ?Channel $target = null,
    ) {
        parent::__construct($kernel);
    }

    #[\Override]
    public function channel(): Channel
    {
        // 本适配器同时服务 AlipayMini / AlipayMp / AlipayApp 三个通道（buildLoginAdapter 注入目标通道），
        // 未注入时保持旧行为返回 AlipayMini，保证向后兼容。
        return $this->target ?? Channel::AlipayMini;
    }

    #[\Override]
    public function authenticate(array $payload): UnionUser
    {
        $code = self::requireString($payload, 'code');

        // 优先使用 payload 中的 channel，其次构造时注入的目标通道，最后默认 AlipayMini
        $channel = isset($payload['channel']) && is_string($payload['channel'])
            ? Channel::from($payload['channel'])
            : $this->channel();

        $provider = $this->provider('alipay');
        $app      = $provider->app();
        if (!$app instanceof AlipayApp) {
            throw new \RuntimeException('支付宝登录要求 alipay Provider');
        }

        $token = $app->auth()->token($code);

        $accessToken = self::str($token, 'access_token');
        $openId      = self::strOrNull($token, 'user_id') ?? self::str($token, 'open_id');

        $raw = $accessToken !== '' ? $app->auth()->user($accessToken) : [];

        return UnionUser::fromRaw(
            channel: $channel,
            openId:  $openId,
            unionId: '',
            raw:     $raw,
            extra:   $token,
        );
    }
}
