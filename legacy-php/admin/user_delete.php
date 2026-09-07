<?php

require __DIR__ . '/_auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$csrf = isset($_GET['csrf']) ? (string) $_GET['csrf'] : '';
if ($id <= 0 || !hash_equals(toolbox_csrf_token(), $csrf) || $id === (int) $adminUser['id']) {
    toolbox_redirect('users.php');
}

$stmt = $adminPdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$id]);
toolbox_redirect('users.php');
