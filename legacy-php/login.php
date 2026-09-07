<?php

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

$config = toolbox_config();
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $result = toolbox_try_login($username, $password, false);
    if ($result['ok']) {
        $back = isset($_POST['back']) ? (string) $_POST['back'] : '';
        toolbox_redirect(
            $back !== '' && preg_match('#^/[^/]#', $back) === 1
                ? $back
                : 'index.php'
        );
    }
    $error = $result['error'];
}

toolbox_start_session();
if (toolbox_current_user()) {
    toolbox_redirect('index.php');
}

$back = isset($_GET['back']) ? (string) $_GET['back'] : '';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>登录 - <?= e($config['siteTitle']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-panel">
        <a class="auth-brand" href="index.php">
            <span class="brand-icon">🏺</span>
            <span><?= e($config['siteName']) ?></span>
        </a>
        <h1>登录账号</h1>
        <?php if ($error !== ''): ?>
            <div class="form-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="login.php" class="auth-form">
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <label>
                <span>用户名</span>
                <input type="text" name="username" value="<?= e($username) ?>" required autocomplete="username">
            </label>
            <label>
                <span>密码</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="auth-submit" type="submit">登录</button>
        </form>
        <p class="auth-switch">没有账号？<a href="register.php">立即注册</a></p>
    </div>
</div>
</body>
</html>
