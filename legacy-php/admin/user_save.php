<?php

require __DIR__ . '/_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    toolbox_redirect('users.php');
}
if (!toolbox_csrf_check()) {
    exit('CSRF 校验失败');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
$nickname = isset($_POST['nickname']) ? trim((string) $_POST['nickname']) : '';
$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$role = isset($_POST['role']) && $_POST['role'] === 'admin' ? 'admin' : 'user';
$isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
$password2 = isset($_POST['password2']) ? (string) $_POST['password2'] : '';

if (preg_match('/^[A-Za-z0-9_]{3,32}$/', $username) !== 1) {
    exit('用户名需为 3-32 位字母、数字或下划线');
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    exit('邮箱格式不正确');
}
if ($password !== '' && strlen($password) < 6) {
    exit('密码至少 6 位');
}
if ($password !== $password2) {
    exit('两次输入的密码不一致');
}

if ($id > 0 && (int) $adminUser['id'] === $id && ($role !== 'admin' || $isActive === 0)) {
    exit('不能取消自己的管理员权限或停用自己的账号');
}

if ($id > 0) {
    $stmt = $adminPdo->prepare(
        'UPDATE users SET username = ?, nickname = ?, email = ?, role = ?, is_active = ?
         WHERE id = ?'
    );
    $stmt->execute([$username, $nickname, $email, $role, $isActive, $id]);
    if ($password !== '') {
        $pwd = $adminPdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $pwd->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }
} else {
    if ($password === '') {
        exit('新增用户必须设置密码');
    }
    try {
        $stmt = $adminPdo->prepare(
            'INSERT INTO users (username, password_hash, nickname, email, role, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $nickname,
            $email,
            $role,
            $isActive,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            exit('用户名已存在');
        }
        throw $exception;
    }
}

toolbox_redirect('users.php');
