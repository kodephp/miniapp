<?php

declare(strict_types=1);

namespace Kode\MiniApp\Utils;

use SimpleXMLElement;

/**
 * XML 工具类
 */
final class Xml
{
    /**
     * 数组转 XML
     *
     * @param array<string, mixed> $data
     */
    public static function build(array $data, string $root = 'xml'): string
    {
        $xml = new SimpleXMLElement("<{$root}/>");
        self::arrayToXml($data, $xml);

        return $xml->asXML() ?: '';
    }

    /**
     * XML 转数组
     *
     * @return array<string, mixed>
     */
    public static function parse(string $xml): array
    {
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($element === false) {
            return [];
        }

        return self::xmlToArray($element);
    }

    /**
     * 递归将数组写入 XML
     *
     * @param array<string, mixed> $data
     */
    private static function arrayToXml(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild((string) $key);
                self::arrayToXml($value, $child);
            } else {
                $xml->addChild((string) $key, (string) $value);
            }
        }
    }

    /**
     * 递归将 XML 转为数组
     *
     * @return array<string, mixed>
     */
    private static function xmlToArray(SimpleXMLElement $xml): array
    {
        $result = [];

        foreach ($xml->children() as $key => $value) {
            $result[$key] = count($value->children()) > 0
                ? self::xmlToArray($value)
                : (string) $value;
        }

        return $result;
    }
}
