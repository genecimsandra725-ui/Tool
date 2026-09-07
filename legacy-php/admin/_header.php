<?php

if (!isset($adminUser, $adminConfig, $adminTitle, $adminCurrent)) {
    exit('后台布局参数缺失');
}
$navItems = [
    ['key' => 'dashboard', 'label' => '数据概览', 'href' => 'index.php'],
    ['key' => 'sites', 'label' => '站点管理', 'href' => 'sites.php'],
    ['key' => 'users', 'label' => '用户管理', 'href' => 'users.php'],
    ['key' => 'feedbacks', 'label' => '反馈工单', 'href' => 'feedbacks.php'],
    ['key' => 'analytics', 'label' => '访问统计', 'href' => 'analytics.php'],
];
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($adminTitle) ?> - 后台管理</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="index.php">
            <span>🏺</span>
            <strong><?= e($adminConfig['siteName']) ?></strong>
        </a>
        <nav class="admin-nav" aria-label="后台导航">
            <?php foreach ($navItems as $item): ?>
                <a class="<?= $adminCurrent === $item['key'] ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>">
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
            <a href="../index.php">返回前台</a>
            <a href="logout.php">退出登录</a>
        </nav>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <h1><?= e($adminTitle) ?></h1>
                <p>管理员：<?= e($adminUser['username']) ?></p>
            </div>
        </header>
