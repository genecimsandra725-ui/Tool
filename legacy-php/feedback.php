<?php

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

$config = toolbox_config();
$user = toolbox_require_login();
$pdo = toolbox_db_required();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!toolbox_csrf_check()) {
        $error = '表单已过期，请刷新后重试';
    } else {
        $type = isset($_POST['type']) ? (string) $_POST['type'] : 'suggestion';
        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
        $content = isset($_POST['content']) ? trim((string) $_POST['content']) : '';
        $siteUrl = isset($_POST['site_url']) ? trim((string) $_POST['site_url']) : '';

        if (!in_array($type, ['broken', 'suggestion', 'other'], true)) {
            $type = 'other';
        }
        $contentLength = function_exists('mb_strlen')
            ? mb_strlen($content, 'UTF-8')
            : strlen($content);
        if ($contentLength < 5) {
            $error = '反馈内容至少 5 个字';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO feedback (user_id, type, title, content, site_url, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $user['id'],
                $type,
                toolbox_truncate($title, 200),
                $content,
                toolbox_truncate($siteUrl, 2048),
                'pending',
            ]);
            $message = '反馈已提交，后台处理后会更新状态';
        }
    }
}

$listStmt = $pdo->prepare(
    'SELECT id, type, title, content, site_url, status, admin_note, created_at, updated_at
     FROM feedback
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 100'
);
$listStmt->execute([(int) $user['id']]);
$feedbackList = $listStmt->fetchAll();

$statusLabels = [
    'pending' => '待处理',
    'processing' => '处理中',
    'resolved' => '已解决',
    'closed' => '已关闭',
];
$typeLabels = [
    'broken' => '链接失效',
    'suggestion' => '建议',
    'other' => '其他',
];
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>我的反馈 - <?= e($config['siteTitle']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php">
            <span class="brand-icon">🏺</span>
            <span class="brand-copy">
                <strong><?= e($config['siteName']) ?></strong>
                <small>实用网站导航</small>
            </span>
        </a>
        <nav class="topbar-info" aria-label="用户菜单">
            <span class="site-total"><?= e($user['nickname'] !== '' ? $user['nickname'] : $user['username']) ?></span>
            <a class="source-link" href="feedback.php">我的反馈</a>
            <a class="source-link" href="logout.php">退出</a>
        </nav>
    </div>
</header>

<main class="feedback-main">
    <h1>用户反馈</h1>
    <?php if ($message !== ''): ?>
        <div class="form-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="form-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="feedback-form-panel" aria-label="提交反馈">
        <h2>提交链接失效或建议</h2>
        <form method="post" action="feedback.php" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= e(toolbox_csrf_token()) ?>">
            <label>
                <span>反馈类型</span>
                <select name="type">
                    <option value="broken">链接失效</option>
                    <option value="suggestion" selected>建议</option>
                    <option value="other">其他</option>
                </select>
            </label>
            <label>
                <span>标题</span>
                <input type="text" name="title" maxlength="200" placeholder="例如：xx 网站无法访问">
            </label>
            <label>
                <span>相关网址</span>
                <input type="url" name="site_url" placeholder="https://example.com">
            </label>
            <label>
                <span>反馈内容</span>
                <textarea name="content" rows="5" required></textarea>
            </label>
            <button class="auth-submit" type="submit">提交反馈</button>
        </form>
    </section>

    <section class="feedback-list-panel" aria-label="我的反馈记录">
        <h2>我的反馈记录</h2>
        <?php if (count($feedbackList) === 0): ?>
            <p class="empty-state">暂无反馈记录</p>
        <?php else: ?>
            <div class="feedback-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>编号</th>
                        <th>类型</th>
                        <th>标题 / 内容</th>
                        <th>状态</th>
                        <th>提交时间</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($feedbackList as $item): ?>
                        <tr>
                            <td>#<?= (int) $item['id'] ?></td>
                            <td><?= e($typeLabels[$item['type']] ?? $item['type']) ?></td>
                            <td>
                                <strong><?= e($item['title'] !== '' ? $item['title'] : '未命名反馈') ?></strong>
                                <div class="cell-sub"><?= nl2br(e($item['content'])) ?></div>
                                <?php if ($item['admin_note'] !== null && $item['admin_note'] !== ''): ?>
                                    <div class="cell-note">后台回复：<?= nl2br(e($item['admin_note'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="status-badge status-<?= e($item['status']) ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span></td>
                            <td><?= e($item['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
