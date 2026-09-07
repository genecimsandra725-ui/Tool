<?php

require __DIR__ . '/_auth.php';

$siteCount = (int) $adminPdo->query('SELECT COUNT(*) FROM sites')->fetchColumn();
$userCount = (int) $adminPdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$visitCount = (int) $adminPdo->query('SELECT COUNT(*) FROM page_visits')->fetchColumn();
$clickCount = (int) $adminPdo->query('SELECT COUNT(*) FROM site_clicks')->fetchColumn();
$pendingFeedback = (int) $adminPdo->query(
    "SELECT COUNT(*) FROM feedback WHERE status = 'pending'"
)->fetchColumn();
$categoryCount = (int) $adminPdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();

$daily = $adminPdo->query(
    'SELECT DATE(visited_at) AS day, COUNT(*) AS total
     FROM page_visits
     WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(visited_at)
     ORDER BY day'
)->fetchAll();

$recentFeedback = $adminPdo->query(
    'SELECT f.*, u.username
     FROM feedback f
     LEFT JOIN users u ON u.id = f.user_id
     ORDER BY f.id DESC
     LIMIT 8'
)->fetchAll();

$adminTitle = '数据概览';
$adminCurrent = 'dashboard';
require __DIR__ . '/_header.php';
?>
<section class="stat-grid">
    <div class="stat-card"><span>站点总数</span><strong><?= $siteCount ?></strong></div>
    <div class="stat-card"><span>分类数量</span><strong><?= $categoryCount ?></strong></div>
    <div class="stat-card"><span>注册用户</span><strong><?= $userCount ?></strong></div>
    <div class="stat-card"><span>访问记录</span><strong><?= $visitCount ?></strong></div>
    <div class="stat-card"><span>站点点击</span><strong><?= $clickCount ?></strong></div>
    <div class="stat-card"><span>待处理工单</span><strong><?= $pendingFeedback ?></strong></div>
</section>

<section class="admin-section">
    <h2>最近 7 天访问趋势</h2>
    <div class="bar-list">
        <?php
        $maxDay = 1;
        foreach ($daily as $row) {
            $maxDay = max($maxDay, (int) $row['total']);
        }
        foreach ($daily as $row):
            $percent = round(((int) $row['total'] / $maxDay) * 100);
        ?>
            <div class="bar-row">
                <span><?= e($row['day']) ?></span>
                <div class="bar-track"><i style="width:<?= (int) $percent ?>%"></i></div>
                <strong><?= (int) $row['total'] ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-section">
    <h2>最新反馈工单</h2>
    <?php if (count($recentFeedback) === 0): ?>
        <p class="muted">暂无反馈</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th>编号</th>
                <th>用户</th>
                <th>类型</th>
                <th>标题</th>
                <th>状态</th>
                <th>时间</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recentFeedback as $item): ?>
                <tr>
                    <td>#<?= (int) $item['id'] ?></td>
                    <td><?= e($item['username'] !== null ? $item['username'] : '游客') ?></td>
                    <td><?= e(admin_feedback_type_text($item['type'])) ?></td>
                    <td><?= e($item['title'] !== '' ? $item['title'] : '未命名') ?></td>
                    <td><span class="status-badge status-<?= e($item['status']) ?>"><?= e(admin_status_text($item['status'])) ?></span></td>
                    <td><?= e($item['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
