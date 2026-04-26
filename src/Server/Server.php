<?php

declare(strict_types=1);

namespace Kode\MiniApp\Server;

use Kode\MiniApp\Contracts\AppInterface;
use Kode\MiniApp\Utils\Xml;

/**
 * 服务端消息处理器
 * 参考 EasyWeChat 的 Server 模式，统一处理各平台的消息推送和事件回调
 */
final class Server
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct(
        private readonly AppInterface $app,
    ) {
    }

    /**
     * 注册消息处理器
     */
    public function on(string $event, callable $handler): self
    {
        $this->handlers[$event] = $handler;

        return $this;
    }

    /**
     * 处理推送消息
     */
    public function serve(): Response
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $content = file_get_contents('php://input') ?: '';

        // 微信/企业微信/QQ 的签名验证
        if ($method === 'GET') {
            return $this->handleVerify();
        }

        // 处理消息体
        $payload = $this->parsePayload($content);
        $event   = $payload['MsgType'] ?? $payload['Event'] ?? 'message';

        $handler = $this->handlers[$event] ?? $this->handlers['message'] ?? null;

        if ($handler === null) {
            return new Response('success');
        }

        $result = $handler($payload, $this->app);

        return new Response((string) $result);
    }

    /**
     * 处理接入验证（微信/企业微信）
     */
    private function handleVerify(): Response
    {
        $signature = $_GET['signature'] ?? '';
        $timestamp = $_GET['timestamp'] ?? '';
        $nonce     = $_GET['nonce'] ?? '';
        $echostr   = $_GET['echostr'] ?? '';

        $config = $this->app->config();
        $token  = $config->get('token', '');

        $tmp = [$token, $timestamp, $nonce];
        sort($tmp, SORT_STRING);

        if (sha1(implode('', $tmp)) === $signature) {
            return new Response($echostr);
        }

        return new Response('');
    }

    /**
     * 解析消息体
     *
     * @return array<string, mixed>
     */
    private function parsePayload(string $content): array
    {
        if (str_starts_with(trim($content), '<')) {
            return Xml::parse($content);
        }

        $json = json_decode($content, true);

        return is_array($json) ? $json : [];
    }
}
