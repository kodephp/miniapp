<?php

declare(strict_types=1);

/**
 * 版本号升级脚本
 * 用法：php scripts/bump-version.php [patch|minor|major]
 * 默认升级 patch
 */

$root = dirname(__DIR__);
$composerFile = $root . '/composer.json';

if (!is_readable($composerFile)) {
    echo "错误：composer.json 文件不存在\n";
    exit(1);
}

$composer = json_decode(file_get_contents($composerFile), true);
if (!isset($composer['version'])) {
    echo "错误：composer.json 中缺少 version 字段\n";
    exit(1);
}

$current = $composer['version'];
$parts   = explode('.', $current);

if (count($parts) !== 3) {
    echo "错误：版本号格式不正确，应为 MAJOR.MINOR.PATCH\n";
    exit(1);
}

$type = $argv[1] ?? 'patch';

[$major, $minor, $patch] = array_map(intval(...), $parts);

match ($type) {
    'major' => [$major, $minor, $patch] = [$major + 1, 0, 0],
    'minor' => [$major, $minor, $patch] = [$major, $minor + 1, 0],
    default => [$major, $minor, $patch] = [$major, $minor, $patch + 1],
};

$newVersion = "{$major}.{$minor}.{$patch}";
$composer['version'] = $newVersion;

file_put_contents(
    $composerFile,
    json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
);

echo "版本号已更新：{$current} -> {$newVersion}\n";
