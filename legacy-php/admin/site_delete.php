<?php

require __DIR__ . '/_auth.php';

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$csrf = isset($_GET['csrf']) ? (string) $_GET['csrf'] : '';
if ($id === '' || !hash_equals(toolbox_csrf_token(), $csrf)) {
    toolbox_redirect('sites.php');
}

$stmt = $adminPdo->prepare('DELETE FROM sites WHERE id = ?');
$stmt->execute([$id]);
toolbox_sync_sites_json();
toolbox_redirect('sites.php');
