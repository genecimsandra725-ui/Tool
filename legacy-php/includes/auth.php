<?php

function toolbox_start_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }
}

function toolbox_current_user()
{
    toolbox_start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $pdo = toolbox_db();
    if (!$pdo) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, username, nickname, email, role, is_active, created_at, last_login_at
         FROM users WHERE id = ? AND is_active = 1'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function toolbox_try_login($username, $password, $requireAdmin = false)
{
    $pdo = toolbox_db_required();
    $stmt = $pdo->prepare(
        'SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify((string) $password, $user['password_hash'])) {
        return ['ok' => false, 'error' => '账号或密码错误'];
    }
    if ($requireAdmin && $user['role'] !== 'admin') {
        return ['ok' => false, 'error' => '该账号没有后台权限'];
    }

    toolbox_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $update->execute([(int) $user['id']]);

    return ['ok' => true, 'user' => $user];
}

function toolbox_logout()
{
    toolbox_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function toolbox_require_login()
{
    $user = toolbox_current_user();
    if ($user) {
        return $user;
    }
    $back = isset($_SERVER['REQUEST_URI']) ? urlencode($_SERVER['REQUEST_URI']) : '';
    header('Location: login.php' . ($back !== '' ? '?back=' . $back : ''));
    exit;
}

function toolbox_require_admin()
{
    $user = toolbox_current_user();
    if ($user && $user['role'] === 'admin') {
        return $user;
    }
    header('Location: login.php');
    exit;
}

function toolbox_csrf_token()
{
    toolbox_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function toolbox_csrf_check()
{
    toolbox_start_session();
    $sent = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

function toolbox_redirect($url)
{
    header('Location: ' . $url);
    exit;
}
