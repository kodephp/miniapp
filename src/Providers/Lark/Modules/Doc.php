<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\Lark\Modules;

use Kode\MiniApp\Providers\Lark\LarkApp;

/**
 * 飞书文档模块
 * 支持创建文档、获取文档内容、创建/更新/删除块等
 */
readonly class Doc
{
    private const string BASE_URL = 'https://open.feishu.cn/open-apis';

    public function __construct(
        private LarkApp $app,
    ) {
    }

    /**
     * 创建文档
     *
     * @return array<string, mixed>
     */
    public function create(string $title, string $folderToken = ''): array
    {
        $token    = $this->app->auth()->token();
        $data     = ['title' => $title];
        if (!empty($folderToken)) {
            $data['folder_token'] = $folderToken;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . '/docx/v1/documents',
            $data,
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取文档元信息
     *
     * @return array<string, mixed>
     */
    public function meta(string $documentId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/docx/v1/documents/{$documentId}",
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取文档纯文本内容
     *
     * @return array<string, mixed>
     */
    public function rawContent(string $documentId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/docx/v1/documents/{$documentId}/raw_content",
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取文档块列表
     *
     * @return array<string, mixed>
     */
    public function blocks(string $documentId, string $blockId, int $pageSize = 500): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/docx/v1/documents/{$documentId}/blocks/{$blockId}/children?page_size={$pageSize}",
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 创建文档块
     *
     * @param array<int, array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public function createBlock(string $documentId, string $blockId, array $children, int $index = 0): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/docx/v1/documents/{$documentId}/blocks/{$blockId}/children?document_revision_id=-1",
            [
                'children' => $children,
                'index'    => $index,
            ],
            headers: ['Authorization' => "Bearer {$token}"]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
