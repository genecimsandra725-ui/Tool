<?php

require __DIR__ . '/_auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $adminPdo->prepare(
    'SELECT f.*, u.username
     FROM feedback f
     LEFT JOIN users u ON u.id = f.user_id
     WHERE f.id = ?'
);
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) {
    toolbox_redirect('feedbacks.php');
}

$adminTitle = '处理反馈 #' . $item['id'];
$adminCurrent = 'feedbacks';
require __DIR__ . '/_header.php';
?>
<a class="btn btn-small" href="feedbacks.php">← 返回工单列表</a>
<section class="admin-section">
    <h2><?= e($item['title'] !== '' ? $item['title'] : '未命名反馈') ?></h2>
    <p class="muted">
        用户：<?= e($item['username'] !== null ? $item['username'] : '已删除用户') ?>
        · 类型：<?= e(admin_feedback_type_text($item['type'])) ?>
        · 提交时间：<?= e($item['created_at']) ?>
    </p>
    <?php if ($item['site_url'] !== ''): ?>
        <p>相关网址：<a href="<?= e($item['site_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($item['site_url']) ?></a></p>
    <?php endif; ?>
    <div class="feedback-content"><?= nl2br(e($item['content'])) ?></div>
</section>

<form class="admin-form" method="post" action="feedback_save.php">
    <input type="hidden" name="csrf_token" value="<?= e(toolbox_csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <div class="form-grid">
        <label>
            <span>工单状态</span>
            <select name="status">
                <?php foreach (['pending', 'processing', 'resolved', 'closed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $item['status'] === $status ? 'selected' : '' ?>>
                        <?= e(admin_status_text($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="span-2">
            <span>处理备注 / 回复用户</span>
            <textarea name="admin_note" rows="6"><?= e($item['admin_note'] ?? '') ?></textarea>
        </label>
    </div>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit">保存处理结果</button>
    </div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
