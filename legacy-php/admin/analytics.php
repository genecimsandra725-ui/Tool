<?php

require __DIR__ . '/_auth.php';

$days = isset($_GET['days']) ? min(90, max(1, (int) $_GET['days'])) : 14;
$since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

$visitTotal = (int) $adminPdo->query('SELECT COUNT(*) FROM page_visits')->fetchColumn();
$uniqueVisitor = (int) $adminPdo->query('SELECT COUNT(DISTINCT ip_hash) FROM page_visits')->fetchColumn();
$clickTotal = (int) $adminPdo->query('SELECT COUNT(*) FROM site_clicks')->fetchColumn();
$feedbackTotal = (int) $adminPdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn();

$dailyStmt = $adminPdo->prepare(
    'SELECT DATE(visited_at) AS day, COUNT(*) AS visits
     FROM page_visits
     WHERE visited_at >= ?
     GROUP BY DATE(visited_at)
     ORDER BY day'
);
$dailyStmt->execute([$since]);
$daily = $dailyStmt->fetchAll();

$categoryStmt = $adminPdo->prepare(
    'SELECT category, COUNT(*) AS visits
     FROM page_visits
     WHERE visited_at >= ?
     GROUP BY category
     ORDER BY visits DESC
     LIMIT 12'
);
$categoryStmt->execute([$since]);
$categories = $categoryStmt->fetchAll();

$topSites = $adminPdo->query(
    'SELECT s.id, s.name, s.url, c2.name AS category_name,
            COUNT(c.id) AS clicks
     FROM site_clicks c
     JOIN sites s ON s.id = c.site_id
     LEFT JOIN categories c2 ON c2.slug = s.category
     GROUP BY c.site_id
     ORDER BY clicks DESC
     LIMIT 20'
)->fetchAll();

$recent = $adminPdo->query(
    'SELECT v.*, s.name AS site_name
     FROM site_clicks v
     LEFT JOIN sites s ON s.id = v.site_id
     ORDER BY v.id DESC
     LIMIT 20'
)->fetchAll();

$adminTitle = '访问统计';
$adminCurrent = 'analytics';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form class="filter-form" method="get" action="analytics.php">
        <label>
            <span>统计天数</span>
            <input type="number" name="days" min="1" max="90" value="<?= $days ?>">
        </label>
        <button type="submit">查看</button>
    </form>
</div>

<section class="stat-grid">
    <div class="stat-card"><span>访问记录</span><strong><?= $visitTotal ?></strong></div>
    <div class="stat-card"><span>独立访客</span><strong><?= $uniqueVisitor ?></strong></div>
    <div class="stat-card"><span>站点点击</span><strong><?= $clickTotal ?></strong></div>
    <div class="stat-card"><span>反馈工单</span><strong><?= $feedbackTotal ?></strong></div>
</section>

<section class="admin-section">
    <h2>最近 <?= $days ?> 天访问</h2>
    <?php
    $maxDay = 1;
    foreach ($daily as $row) {
        $maxDay = max($maxDay, (int) $row['visits']);
    }
    ?>
    <div class="bar-list">
        <?php foreach ($daily as $row): ?>
            <div class="bar-row">
                <span><?= e($row['day']) ?></span>
                <div class="bar-track"><i style="width:<?= round(((int) $row['visits'] / $maxDay) * 100) ?>%"></i></div>
                <strong><?= (int) $row['visits'] ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="admin-columns">
    <section class="admin-section">
        <h2>分类访问分布</h2>
        <table class="admin-table">
            <thead><tr><th>分类</th><th>访问</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $row): ?>
                <tr><td><?= e($row['category']) ?></td><td><?= (int) $row['visits'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="admin-section">
        <h2>站点点击排行</h2>
        <table class="admin-table">
            <thead><tr><th>站点</th><th>分类</th><th>点击</th></tr></thead>
            <tbody>
            <?php if (count($topSites) === 0): ?>
                <tr><td colspan="3" class="muted">暂无点击数据</td></tr>
            <?php endif; ?>
            <?php foreach ($topSites as $row): ?>
                <tr>
                    <td><a href="<?= e($row['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($row['name']) ?></a></td>
                    <td><?= e($row['category_name'] ?? $row['category']) ?></td>
                    <td><?= (int) $row['clicks'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

<section class="admin-section">
    <h2>最近站点点击</h2>
    <table class="admin-table">
        <thead><tr><th>时间</th><th>站点</th><th>目标地址</th><th>来源</th></tr></thead>
        <tbody>
        <?php if (count($recent) === 0): ?>
            <tr><td colspan="4" class="muted">暂无点击记录</td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $row): ?>
            <tr>
                <td><?= e($row['clicked_at']) ?></td>
                <td><?= e($row['site_name'] ?? $row['site_id']) ?></td>
                <td class="cell-url"><?= e($row['target_url']) ?></td>
                <td class="cell-sub"><?= e(toolbox_truncate($row['referer'], 60)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
