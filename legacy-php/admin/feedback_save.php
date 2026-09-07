<?php

require __DIR__ . '/_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    toolbox_redirect('feedbacks.php');
}
if (!toolbox_csrf_check()) {
    exit('CSRF 校验失败');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = isset($_POST['status']) ? (string) $_POST['status'] : 'pending';
$adminNote = isset($_POST['admin_note']) ? trim((string) $_POST['admin_note']) : '';
if (!in_array($status, ['pending', 'processing', 'resolved', 'closed'], true)) {
    $status = 'pending';
}

$stmt = $adminPdo->prepare(
    'UPDATE feedback
     SET status = ?, admin_note = ?, handler_id = ?, updated_at = NOW()
     WHERE id = ?'
);
$stmt->execute([$status, $adminNote, (int) $adminUser['id'], $id]);
toolbox_redirect('feedbacks.php');
