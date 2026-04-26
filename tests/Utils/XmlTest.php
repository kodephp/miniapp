<?php

declare(strict_types=1);

namespace Kode\MiniApp\Tests\Utils;

use Kode\MiniApp\Tests\TestCase;
use Kode\MiniApp\Utils\Xml;

/**
 * Xml 工具类测试
 */
class XmlTest extends TestCase
{
    public function testBuild(): void
    {
        $xml = Xml::build(['name' => 'test', 'value' => 123]);

        self::assertStringContainsString('<name>test</name>', $xml);
        self::assertStringContainsString('<value>123</value>', $xml);
    }

    public function testParse(): void
    {
        $xml   = '<xml><name>test</name><value>123</value></xml>';
        $array = Xml::parse($xml);

        self::assertSame(['name' => 'test', 'value' => '123'], $array);
    }

    public function testParseCdata(): void
    {
        $xml   = '<xml><name><![CDATA[test]]></name></xml>';
        $array = Xml::parse($xml);

        self::assertSame(['name' => 'test'], $array);
    }

    public function testRoundTrip(): void
    {
        $data = ['foo' => 'bar', 'nested' => ['a' => '1']];
        $xml  = Xml::build($data);
        $back = Xml::parse($xml);

        self::assertSame($data, $back);
    }
}
