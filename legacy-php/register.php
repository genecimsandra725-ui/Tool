<?php

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

$config = toolbox_config();
$error = '';
$username = '';
$nickname = '';
$email = '';

toolbox_start_session();
if (toolbox_current_user()) {
    toolbox_redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $nickname = isset($_POST['nickname']) ? trim((string) $_POST['nickname']) : '';
    $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $password2 = isset($_POST['password2']) ? (string) $_POST['password2'] : '';

    if (preg_match('/^[A-Za-z0-9_]{3,32}$/', $username) !== 1) {
        $error = '用户名需为 3-32 位字母、数字或下划线';
    } elseif (strlen($password) < 6) {
        $error = '密码至少 6 位';
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致';
    } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = '邮箱格式不正确';
    } else {
        $pdo = toolbox_db_required();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password_hash, nickname, email, role, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $nickname,
                $email,
                'user',
            ]);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            toolbox_redirect('index.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = '该用户名已被注册';
            } else {
                $error = '注册失败，请稍后重试';
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>注册 - <?= e($config['siteTitle']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-panel">
        <a class="auth-brand" href="index.php">
            <span class="brand-icon">🏺</span>
            <span><?= e($config['siteName']) ?></span>
        </a>
        <h1>创建账号</h1>
        <?php if ($error !== ''): ?>
            <div class="form-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="register.php" class="auth-form">
            <label>
                <span>用户名</span>
                <input type="text" name="username" value="<?= e($username) ?>" required autocomplete="username">
            </label>
            <label>
                <span>昵称</span>
                <input type="text" name="nickname" value="<?= e($nickname) ?>" maxlength="60">
            </label>
            <label>
                <span>邮箱</span>
                <input type="email" name="email" value="<?= e($email) ?>">
            </label>
            <label>
                <span>密码</span>
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label>
                <span>确认密码</span>
                <input type="password" name="password2" required autocomplete="new-password">
            </label>
            <button class="auth-submit" type="submit">注册并登录</button>
        </form>
        <p class="auth-switch">已有账号？<a href="login.php">直接登录</a></p>
    </div>
</div>
</body>
</html>
