<?php

declare(strict_types=1);

namespace Kode\MiniApp\Server;

/**
 * 消息构造器
 * 用于构造各平台的消息回复
 */
final class Message
{
    /**
     * 构造文本消息（微信/企业微信/QQ）
     *
     * @param array<string, mixed> $payload 原始消息体
     * @return array<string, mixed>
     */
    public static function text(string $content, array $payload = []): array
    {
        return [
            'ToUserName'   => $payload['FromUserName'] ?? '',
            'FromUserName' => $payload['ToUserName'] ?? '',
            'CreateTime'   => time(),
            'MsgType'      => 'text',
            'Content'      => $content,
        ];
    }

    /**
     * 构造图片消息
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function image(string $mediaId, array $payload = []): array
    {
        return [
            'ToUserName'   => $payload['FromUserName'] ?? '',
            'FromUserName' => $payload['ToUserName'] ?? '',
            'CreateTime'   => time(),
            'MsgType'      => 'image',
            'Image'        => ['MediaId' => $mediaId],
        ];
    }

    /**
     * 构造图文消息
     *
     * @param array<int, array<string, string>> $articles
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function news(array $articles, array $payload = []): array
    {
        return [
            'ToUserName'   => $payload['FromUserName'] ?? '',
            'FromUserName' => $payload['ToUserName'] ?? '',
            'CreateTime'   => time(),
            'MsgType'      => 'news',
            'ArticleCount' => count($articles),
            'Articles'     => ['item' => $articles],
        ];
    }

    /**
     * 构造被动回复 XML
     *
     * @param array<string, mixed> $data
     */
    public static function toXml(array $data): string
    {
        return \Kode\MiniApp\Utils\Xml::build($data);
    }
}
