<?php

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/auth.php';

$config = toolbox_config();
$error = '';
toolbox_start_session();
$current = toolbox_current_user();
if ($current && $current['role'] === 'admin') {
    toolbox_redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $result = toolbox_try_login($username, $password, true);
    if ($result['ok']) {
        toolbox_redirect('index.php');
    }
    $error = $result['error'];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>后台登录</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-login">
    <form class="admin-login-card" method="post" action="login.php">
        <h1>🏺 <?= e($config['siteName']) ?></h1>
        <h2>后台管理系统</h2>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <label>
            <span>管理员账号</span>
            <input type="text" name="username" required autocomplete="username">
        </label>
        <label>
            <span>密码</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit">进入后台</button>
        <a class="back-link" href="../index.php">返回前台</a>
    </form>
</div>
</body>
</html>
