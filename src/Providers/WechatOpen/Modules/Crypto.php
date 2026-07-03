<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatOpen\Modules;

use Kode\MiniApp\Providers\WechatOpen\WechatOpenApp;

/**
 * 消息加解密模块
 *
 * 实现微信开放平台官方推荐的 AES-256-CBC 加解密：
 *  - AESKey 为 43 位 base64 字符串（不含末尾 "="）
 *  - 解密后用 PKCS#7 padding，明文格式为 random(16B) + msg_len(4B) + msg + receiveid
 *  - 加密时拼装同样的明文格式再做 AES 加密 + base64
 *
 * 适用于：component_verify_ticket 推送、第三方平台授权事件、授权方消息回调等场景。
 */
readonly class Crypto
{
    public function __construct(
        private WechatOpenApp $app,
    ) {
    }

    /**
     * 解密微信推送的加密消息
     *
     * 返回的明文可能是 JSON 字符串（component_verify_ticket / 授权事件），
     * 也可能是 XML 字符串（授权方消息回调），调用方按需解析。
     */
    public function decryptMessage(
        string $encrypted,
        string $msgSignature,
        string $timestamp,
        string $nonce,
    ): string {
        $this->assertSignature($encrypted, $msgSignature, $timestamp, $nonce);

        $key    = $this->key();
        $cipher = base64_decode($encrypted, true);
        if ($cipher === false) {
            throw new \RuntimeException('开放平台消息解密失败：base64 解析错误');
        }

        $decrypted = openssl_decrypt(
            $cipher,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            substr($key, 0, 16)
        );

        if ($decrypted === false) {
            throw new \RuntimeException('开放平台消息解密失败：解密错误');
        }

        return $this->stripPadding($decrypted);
    }

    /**
     * 加密明文（用于被动回复消息）
     */
    public function encryptMessage(
        string $reply,
        string $timestamp,
        string $nonce,
    ): string {
        $key        = $this->key();
        $component  = $this->app->config()->componentAppId();
        $plainBytes = $this->pack($reply, $component);
        $cipher     = openssl_encrypt(
            $plainBytes,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            substr($key, 0, 16)
        );

        if ($cipher === false) {
            throw new \RuntimeException('开放平台消息加密失败');
        }

        $encrypted = base64_encode($cipher);
        $signature = $this->makeSignature($encrypted, $timestamp, $nonce);

        $payload = [
            'Encrypt'      => $encrypted,
            'MsgSignature' => $signature,
            'TimeStamp'    => $timestamp,
            'Nonce'        => $nonce,
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * 校验消息签名
     */
    public function assertSignature(
        string $encrypted,
        string $msgSignature,
        string $timestamp,
        string $nonce,
    ): void {
        $signature = $this->makeSignature($encrypted, $timestamp, $nonce);
        if (!hash_equals($signature, $msgSignature)) {
            throw new \RuntimeException('开放平台消息签名校验失败');
        }
    }

    /**
     * 构造消息签名
     */
    public function makeSignature(
        string $encrypted,
        string $timestamp,
        string $nonce,
    ): string {
        $token = $this->app->config()->token();
        $tmp   = [$token, $timestamp, $nonce, $encrypted];
        sort($tmp, SORT_STRING);

        return sha1(implode('', $tmp));
    }

    /**
     * 获取 32 字节的 AES 密钥
     */
    private function key(): string
    {
        $key = $this->app->config()->aesKey();
        if (str_ends_with($key, '=')) {
            $key = substr($key, 0, -1);
        }
        $binary = base64_decode($key, true);
        if ($binary === false || strlen($binary) !== 32) {
            throw new \RuntimeException('EncodingAESKey 不合法，应为 43 位 base64 字符串');
        }

        return $binary;
    }

    /**
     * 拼装明文：random(16B) + msg_len(4B) + msg + receiveid
     */
    private function pack(string $message, string $receiveId): string
    {
        $random   = random_bytes(16);
        $msgBytes = $message;
        $len      = strlen($msgBytes);
        $lenBytes = pack('N', $len);

        return $random . $lenBytes . $msgBytes . $receiveId;
    }

    /**
     * 去除 PKCS#7 填充并提取明文
     */
    private function stripPadding(string $decrypted): string
    {
        if (strlen($decrypted) < 20) {
            throw new \RuntimeException('开放平台消息解密失败：长度异常');
        }

        $content = substr($decrypted, 16);
        $length  = unpack('N', substr($content, 0, 4));
        if ($length === false) {
            throw new \RuntimeException('开放平台消息解密失败：长度解析异常');
        }

        $msgLength = $length[1];

        return substr($content, 4, $msgLength);
    }
}
