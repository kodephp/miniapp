<?php

declare(strict_types=1);

/**
 * 发布脚本：读取 composer.json 版本并打 tag，推送到远程仓库
 * 用法：php scripts/tag-release.php
 */

$root = dirname(__DIR__);
$composerFile = $root . '/composer.json';

if (!is_readable($composerFile)) {
    echo "错误：composer.json 文件不存在\n";
    exit(1);
}

$composer = json_decode(file_get_contents($composerFile), true);
$version  = $composer['version'] ?? '';

if (empty($version)) {
    echo "错误：composer.json 中缺少 version 字段\n";
    exit(1);
}

$tag = 'v' . $version;

echo "准备发布版本：{$tag}\n";

// 检查是否有未提交的更改
$status = shell_exec('cd ' . escapeshellarg($root) . ' && git status --porcelain 2>/dev/null');
if (!empty($status)) {
    echo "警告：存在未提交的更改，请先提交\n";
    echo $status;
    exit(1);
}

// 打 tag
$cmd = sprintf(
    'cd %s && git tag -a %s -m "Release %s" && git push origin %s',
    escapeshellarg($root),
    escapeshellarg($tag),
    escapeshellarg($tag),
    escapeshellarg($tag)
);

echo "执行：{$cmd}\n";
$output = shell_exec($cmd);
echo $output ?? '';

echo "发布完成：{$tag}\n";
