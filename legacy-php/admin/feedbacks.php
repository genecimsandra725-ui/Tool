<?php

require __DIR__ . '/_auth.php';

$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : '';
$typeFilter = isset($_GET['type']) ? (string) $_GET['type'] : '';

$sql = 'SELECT f.*, u.username
        FROM feedback f
        LEFT JOIN users u ON u.id = f.user_id
        WHERE 1 = 1';
$params = [];
if ($statusFilter !== '' && $statusFilter !== 'all') {
    $sql .= ' AND f.status = ?';
    $params[] = $statusFilter;
}
if ($typeFilter !== '' && $typeFilter !== 'all') {
    $sql .= ' AND f.type = ?';
    $params[] = $typeFilter;
}
$sql .= ' ORDER BY f.status = "pending" DESC, f.id DESC';
$stmt = $adminPdo->prepare($sql);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

$adminTitle = '反馈工单';
$adminCurrent = 'feedbacks';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form class="filter-form" method="get" action="feedbacks.php">
        <select name="status">
            <option value="all">全部状态</option>
            <?php foreach (['pending', 'processing', 'resolved', 'closed'] as $status): ?>
                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                    <?= e(admin_status_text($status)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="type">
            <option value="all">全部类型</option>
            <?php foreach (['broken', 'suggestion', 'other'] as $type): ?>
                <option value="<?= e($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>>
                    <?= e(admin_feedback_type_text($type)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">筛选</button>
    </form>
</div>

<section class="admin-section">
    <table class="admin-table">
        <thead>
        <tr>
            <th>编号</th>
            <th>用户</th>
            <th>类型</th>
            <th>内容</th>
            <th>状态</th>
            <th>时间</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($feedbacks) === 0): ?>
            <tr><td colspan="7" class="muted">没有反馈工单</td></tr>
        <?php endif; ?>
        <?php foreach ($feedbacks as $item): ?>
            <tr>
                <td>#<?= (int) $item['id'] ?></td>
                <td><?= e($item['username'] !== null ? $item['username'] : '已删除用户') ?></td>
                <td><?= e(admin_feedback_type_text($item['type'])) ?></td>
                <td>
                    <strong><?= e($item['title'] !== '' ? $item['title'] : '未命名反馈') ?></strong>
                    <div class="cell-sub"><?= e(toolbox_truncate($item['content'], 80)) ?></div>
                </td>
                <td><span class="status-badge status-<?= e($item['status']) ?>"><?= e(admin_status_text($item['status'])) ?></span></td>
                <td><?= e($item['created_at']) ?></td>
                <td class="row-actions">
                    <a class="btn btn-small" href="feedback_edit.php?id=<?= (int) $item['id'] ?>">处理</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
