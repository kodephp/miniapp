<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Core;

use GuzzleHttp\Psr7\Response;
use Kode\MiniApp\Contracts\Platform;
use Kode\MiniApp\Core\ApiResponse;
use Kode\MiniApp\Exceptions\ApiException;
use Kode\MiniApp\Tests\TestCase;

/**
 * 统一响应 ApiResponse 测试
 */
class ApiResponseTest extends TestCase
{
    public function testWechatSuccessAndFailure(): void
    {
        $ok = ApiResponse::fromArray(['errcode' => 0, 'errmsg' => 'ok'], Platform::Wechat);
        self::assertTrue($ok->isSuccessful());
        self::assertNull($ok->errorCode());

        $fail = ApiResponse::fromArray(['errcode' => 40001, 'errmsg' => 'invalid token'], Platform::Wechat);
        self::assertFalse($fail->isSuccessful());
        self::assertSame(40001, $fail->errorCode());
        self::assertSame('invalid token', $fail->errorMessage());
    }

    public function testDouyinErrorField(): void
    {
        $ok = ApiResponse::fromArray(['err_no' => 0, 'err_tips' => ''], Platform::Douyin);
        self::assertTrue($ok->isSuccessful());

        $fail = ApiResponse::fromArray(['err_no' => 1, 'err_tips' => 'bad'], Platform::Douyin);
        self::assertFalse($fail->isSuccessful());
        self::assertSame(1, $fail->errorCode());
        self::assertSame('bad', $fail->errorMessage());
    }

    public function testBaiduErrorField(): void
    {
        $ok = ApiResponse::fromArray(['errno' => 0, 'errmsg' => 'ok'], Platform::Baidu);
        self::assertTrue($ok->isSuccessful());

        $fail = ApiResponse::fromArray(['errno' => 1, 'errmsg' => 'x'], Platform::Baidu);
        self::assertSame(1, $fail->errorCode());
    }

    public function testLarkCodeField(): void
    {
        // 飞书的 code 才是错误码
        $ok = ApiResponse::fromArray(['code' => 0, 'msg' => 'ok', 'data' => ['uid' => 1]], Platform::Lark);
        self::assertTrue($ok->isSuccessful());

        $fail = ApiResponse::fromArray(['code' => 99991663, 'msg' => 'token expired'], Platform::Lark);
        self::assertFalse($fail->isSuccessful());
        self::assertSame(99991663, $fail->errorCode());
    }

    public function testNonLarkCodeFieldIsBusinessData(): void
    {
        // 非飞书平台、且无 msg 字段时，code 不应被当作错误码
        $resp = ApiResponse::fromArray(['data' => ['code' => 0, 'openid' => 'x']], Platform::Wechat);
        self::assertTrue($resp->isSuccessful());
        self::assertSame(0, $resp->get('data.code'));
    }

    public function testOAuthErrorField(): void
    {
        $fail = ApiResponse::fromArray(['error' => 'invalid_grant', 'error_description' => 'bad'], Platform::Baidu);
        self::assertFalse($fail->isSuccessful());
        self::assertSame('invalid_grant', $fail->errorCode());
    }

    public function testAlipaySuccess(): void
    {
        $resp = ApiResponse::fromArray([
            'alipay_trade_create_response' => [
                'code'     => '10000',
                'msg'      => 'Success',
                'trade_no' => 'TN_001',
            ],
            'sign' => 'abc',
        ], Platform::Alipay);

        self::assertTrue($resp->isSuccessful());
        self::assertNull($resp->errorCode());
        self::assertSame('TN_001', $resp->payload()['trade_no']);
        self::assertSame(['code' => '10000', 'msg' => 'Success', 'trade_no' => 'TN_001'], $resp->payload());
    }

    public function testAlipayFailureUsesSubCode(): void
    {
        $resp = ApiResponse::fromArray([
            'alipay_trade_create_response' => [
                'code'     => '40004',
                'msg'      => 'Business Failed',
                'sub_code' => 'TRADE_NOT_EXIST',
                'sub_msg'  => '交易不存在',
            ],
        ], Platform::Alipay);

        self::assertFalse($resp->isSuccessful());
        self::assertSame('TRADE_NOT_EXIST', $resp->errorCode());
        self::assertSame('交易不存在', $resp->errorMessage());
    }

    public function testAccessors(): void
    {
        $resp = ApiResponse::fromArray([
            'errcode' => 0,
            'data'    => ['openid' => 'o1', 'count' => '12', 'list' => [1, 2]],
        ], Platform::Wechat);

        self::assertSame('o1', $resp->get('data.openid'));
        self::assertSame('o1', $resp->string('data.openid'));
        self::assertSame(12, $resp->int('data.count'));
        self::assertSame([1, 2], $resp->array('data.list'));
        self::assertTrue($resp->has('data.openid'));
        self::assertFalse($resp->has('data.missing'));
        self::assertSame('fallback', $resp->get('nope', 'fallback'));
    }

    public function testThrowIfFailed(): void
    {
        $ok = ApiResponse::fromArray(['errcode' => 0], Platform::Wechat);
        self::assertSame($ok, $ok->throwIfFailed('微信登录'));

        $fail = ApiResponse::fromArray(['errcode' => 40001, 'errmsg' => 'x'], Platform::Wechat);
        $this->expectException(ApiException::class);
        $fail->throwIfFailed('微信登录');
    }

    public function testArrayAccessAndJsonSerialize(): void
    {
        $resp = ApiResponse::fromArray(['errcode' => 0, 'foo' => 'bar'], Platform::Wechat);
        self::assertTrue(isset($resp['foo']));
        self::assertSame('bar', $resp['foo']);
        self::assertSame(['errcode' => 0, 'foo' => 'bar'], $resp->jsonSerialize());
        self::assertJsonStringEqualsJsonString('{"errcode":0,"foo":"bar"}', (string) json_encode($resp));
    }

    public function testFromPsrAndInvalidJson(): void
    {
        $resp = ApiResponse::fromPsr(new Response(200, [], (string) json_encode(['errcode' => 0])), Platform::Wechat);
        self::assertTrue($resp->isSuccessful());

        // 非法 JSON 不应触发告警，应得到空数据
        $bad = ApiResponse::fromPsr(new Response(200, [], 'not-json'), Platform::Wechat);
        self::assertSame([], $bad->toArray());
        self::assertTrue($bad->isSuccessful());

        // HTTP 4xx/5xx 直接判定失败
        $err = ApiResponse::fromPsr(new Response(500, [], '{}'), Platform::Wechat);
        self::assertFalse($err->isSuccessful());
    }
}
