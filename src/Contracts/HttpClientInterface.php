<?php

declare(strict_types=1);

namespace Kode\MiniApp\Contracts;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP 客户端接口
 */
interface HttpClientInterface
{
    /**
     * 发送 GET 请求
     *
     * @param array<string, mixed> $options
     */
    public function get(string $uri, array $options = []): ResponseInterface;

    /**
     * 发送 POST 请求
     *
     * @param array<string, mixed> $options
     */
    public function post(string $uri, array $options = []): ResponseInterface;

    /**
     * 发送 JSON POST 请求
     *
     * @param array<string, mixed> $data
     */
    public function postJson(string $uri, array $data = [], array $headers = []): ResponseInterface;

    /**
     * 上传文件
     *
     * @param array<string, mixed> $form
     */
    public function upload(string $uri, string $field, string $filePath, array $form = []): ResponseInterface;
}
