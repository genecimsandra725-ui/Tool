<?php

require __DIR__ . '/_auth.php';

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filterCategory = isset($_GET['category']) ? (string) $_GET['category'] : '';

$sql = 'SELECT s.*, c.name AS category_name FROM sites s
        LEFT JOIN categories c ON c.slug = s.category
        WHERE 1 = 1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (s.name LIKE ? OR s.description LIKE ? OR s.url LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($filterCategory !== '' && $filterCategory !== 'all') {
    $sql .= ' AND s.category = ?';
    $params[] = $filterCategory;
}
$sql .= ' ORDER BY s.category ASC, s.sort_order ASC, s.id ASC';

$stmt = $adminPdo->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll();

$categories = $adminPdo->query(
    'SELECT slug, name FROM categories ORDER BY sort_order'
)->fetchAll();

$adminTitle = '站点管理';
$adminCurrent = 'sites';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form class="filter-form" method="get" action="sites.php">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="搜索站点名称、简介、网址">
        <select name="category">
            <option value="all">全部分类</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category['slug']) ?>" <?= $filterCategory === $category['slug'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">筛选</button>
    </form>
    <a class="btn btn-primary" href="site_edit.php">新增站点</a>
</div>

<section class="admin-section">
    <table class="admin-table site-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>站点</th>
            <th>分类</th>
            <th>状态</th>
            <th>点赞 / 点击</th>
            <th>更新时间</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($sites) === 0): ?>
            <tr><td colspan="7" class="muted">没有找到站点</td></tr>
        <?php endif; ?>
        <?php foreach ($sites as $site): ?>
            <tr>
                <td><?= e($site['id']) ?></td>
                <td>
                    <strong><?= e($site['name']) ?></strong>
                    <div class="cell-sub"><?= e($site['url']) ?></div>
                </td>
                <td><?= e($site['category_name'] ?? $site['category']) ?></td>
                <td><?= (int) $site['is_active'] === 1 ? '显示' : '隐藏' ?></td>
                <td><?= (int) $site['like_count'] ?> / <?= (int) $site['click_count'] ?></td>
                <td><?= e($site['updated_at']) ?></td>
                <td class="row-actions">
                    <a class="btn btn-small" href="site_edit.php?id=<?= e($site['id']) ?>">编辑</a>
                    <a class="btn btn-small btn-danger" href="site_delete.php?id=<?= e($site['id']) ?>&csrf=<?= e(toolbox_csrf_token()) ?>"
                       onclick="return confirm('确认删除该站点？删除后需重新同步 JSON。')">删除</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
