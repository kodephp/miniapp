<?php

declare(strict_types=1);

namespace Kode\MiniApp\Providers\WechatWork\Modules;

use Kode\MiniApp\Providers\WechatWork\WechatWorkApp;

/**
 * 企业微信外部联系人管理模块（客户联系）
 */
readonly class ExternalContact
{
    private const BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function __construct(
        private WechatWorkApp $app,
    ) {
    }

    /**
     * 获取配置了客户联系功能的成员列表
     *
     * @return array<string>
     */
    public function getFollowUserList(): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/externalcontact/get_follow_user_list?access_token={$token}"
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['follow_user'] ?? [];
    }

    /**
     * 获取指定成员添加的客户列表
     *
     * @return array<string>
     */
    public function list(string $userid): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->get(
            self::BASE_URL . "/externalcontact/list?access_token={$token}&userid={$userid}"
        );
        $data = json_decode((string) $response->getBody(), true);

        return $data['external_userid'] ?? [];
    }

    /**
     * 获取客户详情
     *
     * @return array<string, mixed>
     */
    public function get(string $externalUserid, string $cursor = ''): array
    {
        $token    = $this->app->auth()->token();
        $url      = self::BASE_URL . "/externalcontact/get?access_token={$token}&external_userid={$externalUserid}";
        if (!empty($cursor)) {
            $url .= "&cursor={$cursor}";
        }
        $response = $this->app->http()->get($url);
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("获取客户详情失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 批量获取客户详情
     *
     * @param array<int, string> $userids
     * @return array<string, mixed>
     */
    public function batchGetByUser(array $userids, string $cursor = '', int $limit = 100): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/batchgetbyuser?access_token={$token}",
            [
                'userid_list' => $userids,
                'cursor'      => $cursor,
                'limit'       => $limit,
            ]
        );
        $data = json_decode((string) $response->getBody(), true);

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException("批量获取客户详情失败: [{$data['errcode']}] {$data['errmsg']}");
        }

        return $data;
    }

    /**
     * 修改客户备注信息
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function remark(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/remark?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取企业标签库
     *
     * @param array<int, string>|null $tagIds
     * @return array<string, mixed>
     */
    public function getCorpTagList(?array $tagIds = null): array
    {
        $token    = $this->app->auth()->token();
        $data     = [];
        if ($tagIds !== null) {
            $data['tag_id'] = $tagIds;
        }
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/get_corp_tag_list?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 添加企业客户标签
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addCorpTag(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/add_corp_tag?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 编辑企业客户标签
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function editCorpTag(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/edit_corp_tag?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除企业客户标签
     *
     * @param array<int, string> $tagIds
     * @param array<int, string> $groupIds
     * @return array<string, mixed>
     */
    public function delCorpTag(array $tagIds, array $groupIds = []): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/del_corp_tag?access_token={$token}",
            [
                'tag_id'   => $tagIds,
                'group_id' => $groupIds,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 配置客户联系「联系我」方式
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function addContactWay(array $config): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/add_contact_way?access_token={$token}",
            $config
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取企业已配置的「联系我」方式
     *
     * @return array<string, mixed>
     */
    public function getContactWay(string $configId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/get_contact_way?access_token={$token}",
            ['config_id' => $configId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 更新企业已配置的「联系我」方式
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function updateContactWay(array $config): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/update_contact_way?access_token={$token}",
            $config
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 删除企业已配置的「联系我」方式
     *
     * @return array<string, mixed>
     */
    public function deleteContactWay(string $configId): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/del_contact_way?access_token={$token}",
            ['config_id' => $configId]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 发送欢迎语
     *
     * @param array<string, mixed> $welcome
     * @return array<string, mixed>
     */
    public function sendWelcomeMsg(array $welcome): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/send_welcome_msg?access_token={$token}",
            $welcome
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取离职成员的客户列表
     *
     * @return array<string, mixed>
     */
    public function getUnassignedList(int $pageId = 0, int $pageSize = 1000): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/get_unassigned_list?access_token={$token}",
            ['page_id' => $pageId, 'page_size' => $pageSize]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 分配离职成员的客户
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function transfer(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/transfer?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 分配离职成员的客户群
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function transferGroupChat(array $data): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/groupchat/transfer?access_token={$token}",
            $data
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取客户群列表
     *
     * @param array<string, string> $ownerFilter
     * @return array<string, mixed>
     */
    public function groupChatList(int $limit = 100, string $cursor = '', array $ownerFilter = []): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/groupchat/list?access_token={$token}",
            [
                'limit'          => $limit,
                'cursor'         => $cursor,
                'owner_filter'   => $ownerFilter,
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * 获取客户群详情
     *
     * @return array<string, mixed>
     */
    public function groupChatGet(string $chatId, int $needName = 1): array
    {
        $token    = $this->app->auth()->token();
        $response = $this->app->http()->postJson(
            self::BASE_URL . "/externalcontact/groupchat/get?access_token={$token}",
            ['chat_id' => $chatId, 'need_name' => $needName]
        );

        return json_decode((string) $response->getBody(), true);
    }
}
