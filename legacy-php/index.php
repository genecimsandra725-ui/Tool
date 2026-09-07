<?php

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

$config = toolbox_config();
$currentUser = toolbox_current_user();
$categories = toolbox_categories();
$allSites = toolbox_all_sites();
$categoryCounts = toolbox_category_counts();

$activeSlug = isset($_GET['cat']) ? trim((string) $_GET['cat']) : 'all';
$activeCategory = toolbox_find_category($activeSlug);
if ($activeCategory === null && $activeSlug !== 'all') {
    $activeSlug = 'all';
}

$sites = toolbox_selected_sites($activeSlug);
$totalSites = count($allSites);

if ($activeSlug === 'all') {
    $pageTitle = $config['siteTitle'];
    $headingTitle = '全部站点';
    $headingIcon = '🗂️';
    $headingDesc = '阿旻同学整理的完整网站导航，共 ' . $totalSites . ' 个站点。';
    $pageDescription = $config['siteDescription'];
    $pageKeywords = $config['siteKeywords'];
} else {
    $pageTitle = $activeCategory['name'] . ' - ' . $config['siteTitle'];
    $headingTitle = $activeCategory['name'];
    $headingIcon = $activeCategory['icon'];
    $headingDesc = $activeCategory['desc'];
    $pageDescription = $headingTitle . '分类：' . $activeCategory['desc'] . '，来自' . $config['siteName'] . '。';
    $pageKeywords = $config['siteKeywords'] . ',' . $activeCategory['name'];
}

toolbox_track_page_view($activeSlug);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $config['siteBaseUrl'] !== ''
    ? rtrim($config['siteBaseUrl'], '/')
    : $scheme . '://' . $host;
$scriptPath = ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
$canonicalUrl = $baseUrl . $scriptPath;
if ($activeSlug !== 'all') {
    $canonicalUrl .= '?cat=' . rawurlencode($activeSlug);
}

$itemListItems = array_slice($sites, 0, 60);
$itemListJson = [];
foreach ($itemListItems as $itemIndex => $item) {
    $itemListJson[] = [
        '@type' => 'ListItem',
        'position' => $itemIndex + 1,
        'url' => $item['url'],
        'name' => $item['name'],
    ];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="keywords" content="<?= e($pageKeywords) ?>">
    <meta name="robots" content="index,follow">
    <meta name="author" content="<?= e($config['siteName']) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($config['siteName']) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:locale" content="zh_CN">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <title><?= e($pageTitle) ?></title>
    <script type="application/ld+json">
    <?= json_encode(
        [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $pageTitle,
            'description' => $pageDescription,
            'url' => $canonicalUrl,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $itemListJson,
            ],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <button id="navToggle" class="nav-toggle" type="button" aria-label="展开分类" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18"></path>
            </svg>
        </button>
        <a class="brand" href="index.php" aria-label="<?= e($config['siteName']) ?>首页">
            <span class="brand-icon">🏺</span>
            <span class="brand-copy">
                <strong><?= e($config['siteName']) ?></strong>
                <small>实用网站导航</small>
            </span>
        </a>
        <nav class="topbar-info" aria-label="用户菜单">
            <span class="site-total"><?= $totalSites ?> 个站点</span>
            <?php if ($currentUser): ?>
                <span class="user-welcome"><?= e($currentUser['nickname'] !== '' ? $currentUser['nickname'] : $currentUser['username']) ?></span>
                <a class="source-link" href="feedback.php">我的反馈</a>
                <a class="source-link" href="logout.php">退出</a>
            <?php else: ?>
                <a class="source-link" href="login.php">登录</a>
                <a class="source-link" href="register.php">注册</a>
            <?php endif; ?>
            <a class="source-link" href="feedback.php">反馈</a>
        </nav>
    </div>
</header>

<div id="sidebarBackdrop" class="sidebar-backdrop" hidden></div>

<div class="layout">
    <aside id="sidebar" class="sidebar" aria-label="网站分类导航">
        <div class="sidebar-head">
            <span>网站分类</span>
        </div>
        <nav class="category-nav" aria-label="分类">
            <a class="category-link<?= $activeSlug === 'all' ? ' is-active' : '' ?>" href="index.php">
                <span class="category-icon">🗂️</span>
                <span class="category-name">全部站点</span>
                <span class="category-count"><?= $totalSites ?></span>
            </a>
            <?php foreach ($categories as $category): ?>
                <?php
                $slug = $category['slug'];
                $count = isset($categoryCounts[$slug]) ? $categoryCounts[$slug] : 0;
                ?>
                <a
                    class="category-link<?= $activeSlug === $slug ? ' is-active' : '' ?>"
                    href="index.php?cat=<?= e($slug) ?>"
                >
                    <span class="category-icon"><?= e($category['icon']) ?></span>
                    <span class="category-name"><?= e($category['name']) ?></span>
                    <span class="category-count"><?= $count ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <button id="sidebarCollapse" class="sidebar-collapse" type="button" aria-label="折叠侧栏">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="m15 18-6-6 6-6"></path>
            </svg>
            <span>收起侧栏</span>
        </button>
    </aside>

    <main class="main-content">
        <nav class="breadcrumb" aria-label="面包屑">
            <a href="index.php">首页</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= e($headingTitle) ?></span>
        </nav>
        <div class="page-heading">
            <div class="heading-row">
                <h1><span class="heading-icon"><?= e($headingIcon) ?></span><?= e($headingTitle) ?></h1>
                <span class="result-count"><?= count($sites) ?> / <?= $totalSites ?></span>
            </div>
            <p class="heading-desc"><?= e($headingDesc) ?></p>
        </div>

        <?php if (count($sites) === 0): ?>
            <div class="empty-state">该分类暂无站点</div>
        <?php else: ?>
            <section class="site-grid" aria-label="<?= e($headingTitle) ?>站点列表">
                <?php toolbox_render_cards($sites); ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<footer class="site-footer">
    <div class="footer-friends" aria-label="友情链接">
        <?php foreach ($config['friendLinks'] as $friend): ?>
            <a href="<?= e($friend['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($friend['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <p>
        <?= e($config['siteName']) ?> · 更新于 <?= e($config['updatedAt']) ?>
        · 数据整理自<a class="footer-source" href="<?= e($config['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer">飞书文档</a>
    </p>
    <p class="footer-icp">
        <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= e($config['icpNumber']) ?></a>
    </p>
</footer>

<script src="assets/vendor/jquery-3.7.1.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
