<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Union\Bridge;

use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\Union\Bridge\Fixtures\FakePaysHttpClient;
use Kode\MiniApp\Union\Bridge\PaysBridge;
use Kode\MiniApp\Union\Bridge\PaysBridgePayAdapter;
use Kode\MiniApp\Union\Channel;
use Kode\Pays\Facade\Pay;
use Kode\Pays\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * 结算（Settlement）端到端（签名拼装全链路）验证。
 *
 * 与 {@see \Kode\MiniApp\Tests\Union\Bridge\PaysBridgeAdvancedTest} 形成关键互补：原测试仅断言
 * 「端点 + return_code」，从未用同一 api_key 重新校验真实网关生成的 MD5 签名。
 *
 * 本测试经真实 WechatPayGateway + FakePaysHttpClient 走通「参数校验 / 签名 / 报文拼装 / 响应解析」
 * 真实代码路径而不触网，覆盖三个结算方法各自的出站端点（均为微信 V2 XML + MD5 签名，需证书但
 * 签名在证书附加之前完成，故离线可测）：
 *  - settlementToWallet   → mmpaymkttransfers/promotion/transfers（复用企业付款到零钱，结算到微信零钱）
 *  - settlementToBankCard → mmpaymkttransfers/pay_bank（复用企业付款到银行卡，卡号与姓名走 RSA 加密）
 *  - settlementToPayout   → 微信无外部账户 Payout 语义，网关抛 methodNotSupported，经 invokeGateway
 *                           归一为 ApiException（与 pauseSubscription 同模式的「不支持能力归一」证据）
 *
 * querySettlement 复用 queryTransfer（微信 V3 GET，需 serial_no + private_key 证书），离线无法构造
 * 合法 V3 授权头，故不在本批离线签名链覆盖范围内（留待证书环境专项测试）。
 *
 * 断言核心：真实网关对出站请求做了 MD5 签名（Signer::md5），且签名可用同一 api_key 经
 * Signer::verifyMd5 重新校验通过。
 */
final class PaysBridgeSettlementSignChainTest extends TestCase
{
    /**
     * 微信 V2 响应用于验签的 api_key（须与传给网关的 config.api_key 完全一致）
     */
    private const API_KEY = 'unit_test_api_key_0123456789';

    private FakePaysHttpClient $fake;

    protected function setUp(): void
    {
        $this->fake = new FakePaysHttpClient(self::API_KEY);
        Pay::setHttpClient($this->fake);
        Pay::clearCache();
    }

    protected function tearDown(): void
    {
        Pay::clearCache();
    }

    /**
     * @return array<string, mixed>
     */
    private function wechatConfig(): array
    {
        return ['app_id' => 'wx_app', 'mch_id' => 'mch_1', 'api_key' => self::API_KEY];
    }

    /**
     * 将微信 V2 出站 XML 解析为关联数组（剥离 CDATA，空元素归一为空字符串）。
     *
     * @return array<string, string>
     */
    private function parseWechatXml(string $xml): array
    {
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);
        \assert($doc !== false);

        $arr = [];
        foreach ($doc->children() as $name => $node) {
            $arr[$name] = (string) $node;
        }

        return $arr;
    }

    public function testSettlementToWalletSignsOutboundPromotionTransfersViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        // settlementToWallet 复用 singleTransfer（企业付款到零钱），account 即 openid，
        // real_name 经 FORCE_CHECK 必填（映射 re_user_name）
        $result = $adapter->settlementToWallet([
            'out_biz_no' => 'S_W_20260816_001',
            'amount'     => 100,
            'account'    => 'OPENID_XYZ',
            'real_name'  => '张三',
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 mmpaymkttransfers/promotion/transfers（与单笔转账同一通道）
        self::assertStringContainsString('mmpaymkttransfers/promotion/transfers', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 mmpaymkttransfers/promotion/transfers 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('S_W_20260816_001', $req['partner_trade_no'] ?? '');
        self::assertSame('OPENID_XYZ', $req['openid'] ?? '');
        self::assertSame('100', $req['amount'] ?? '');
        self::assertSame('张三', $req['re_user_name'] ?? '');

        // 全链路核心：结算到零钱请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站结算到零钱请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站结算到零钱请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testSettlementToBankCardSignsOutboundPayBankViaRealGateway(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        // settlementToBankCard 复用 withdraw（企业付款到银行卡）
        $result = $adapter->settlementToBankCard([
            'out_biz_no'   => 'S_B_20260816_001',
            'amount'       => 5000,
            'bank_card_no' => '6228480402564890018',
            'real_name'    => '张三',
        ]);

        self::assertSame('SUCCESS', $result['return_code'] ?? null);

        // 出站端点必须是 mmpaymkttransfers/pay_bank（与企业付款到银行卡同一通道）
        self::assertStringContainsString('mmpaymkttransfers/pay_bank', $this->fake->lastUrl ?? '');

        $body = (string) ($this->fake->lastRawBody ?? '');
        self::assertNotEmpty($body, '真实网关必须向 mmpaymkttransfers/pay_bank 发送 XML 报文');

        $req = $this->parseWechatXml($body);

        self::assertSame('S_B_20260816_001', $req['partner_trade_no'] ?? '');
        self::assertSame('5000', $req['amount'] ?? '');
        // 未配置 bank_public_key 时 encryptBankCard 退化为 base64，仍应产出非空密文
        self::assertNotEmpty($req['enc_bank_no'] ?? '');
        self::assertNotEmpty($req['enc_true_name'] ?? '');

        // 全链路核心：结算到银行卡请求必须做了 MD5 签名
        self::assertArrayHasKey('sign', $req, '真实网关必须对出站结算到银行卡请求做了 MD5 签名');
        self::assertTrue(
            Signer::verifyMd5($req, self::API_KEY),
            '出站结算到银行卡请求签名必须可用 api_key 重新校验通过',
        );
    }

    public function testSettlementToPayoutNormalizedToApiException(): void
    {
        $adapter = PaysBridge::adapter(Channel::WechatMini, fn () => $this->wechatConfig());

        self::assertInstanceOf(PaysBridgePayAdapter::class, $adapter);

        // 微信无外部账户 Payout 语义，网关抛 PayException::methodNotSupported，
        // 经 PaysBridge::invokeGateway 归一为 ApiException（无静默成功）。
        $this->expectException(ApiException::class);

        $adapter->settlementToPayout(['out_biz_no' => 'S_P_20260816_001']);
    }
}
