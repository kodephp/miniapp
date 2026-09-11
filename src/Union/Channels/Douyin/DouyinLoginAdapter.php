<?php

declare(strict_types=1);

namespace Kode\MiniApp\Union\Channels\Douyin;

use Kode\MiniApp\Contracts\PlatformInterface;
use Kode\MiniApp\Providers\Douyin\DouyinApp;
use Kode\MiniApp\Union\Channels\BaseAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\MiniApp\Union\Contracts\LoginAdapter;
use Kode\MiniApp\Union\UnionUser;

/**
 * 抖音登录适配器（支持抖音小程序 / 头条号 / 西瓜视频）
 *
 * 业务流程：
 *   1. 客户端调 tt.login 拿到 code / anonymousCode
 *   2. 服务端调 /api/apps/v2/jscode2session 拿 openid / unionid / session_key
 *
 * 业务侧调用：
 *   $user = $kernel->union()->authenticate(Channel::DouyinMini, ['code' => 'xxx']);
 */
final class DouyinLoginAdapter extends BaseAdapter implements LoginAdapter
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
        // 本适配器同时服务 DouyinMini / DouyinMp 两个通道（buildLoginAdapter 注入目标通道），
        // 未注入时保持旧行为返回 DouyinMini，保证向后兼容。
        return $this->target ?? Channel::DouyinMini;
    }

    #[\Override]
    public function authenticate(array $payload): UnionUser
    {
        $code         = self::requireString($payload, 'code');
        $anonymousCode = is_string($payload['anonymous_code'] ?? null) ? $payload['anonymous_code'] : '';

        // 优先使用 payload 中的 channel，其次构造时注入的目标通道，最后默认 DouyinMini
        $channel = isset($payload['channel']) && is_string($payload['channel'])
            ? Channel::from($payload['channel'])
            : $this->channel();

        $provider = $this->provider('douyin');
        $app      = $provider->app();
        if (!$app instanceof DouyinApp) {
            throw new \RuntimeException('抖音登录要求 douyin Provider');
        }

        $session = $app->auth()->session($code, $anonymousCode);

        $openId  = self::str($session, 'openid');
        $unionId = self::strOrNull($session, 'unionid') ?? '';

        return UnionUser::fromRaw(
            channel: $channel,
            openId:  $openId,
            unionId: $unionId,
            raw:     $session,
            extra:   $session,
        );
    }
}
