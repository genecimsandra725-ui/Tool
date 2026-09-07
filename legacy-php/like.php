<?php

require __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
if ($id === '' || preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_id']);
    exit;
}

$pdo = toolbox_db();
if ($pdo) {
    try {
        $stmt = $pdo->prepare(
            'UPDATE sites SET like_count = like_count + 1 WHERE id = ?'
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'site_not_found']);
            exit;
        }
        $fetch = $pdo->prepare('SELECT like_count FROM sites WHERE id = ?');
        $fetch->execute([$id]);
        $count = (int) $fetch->fetchColumn();
        echo json_encode(['ok' => true, 'id' => $id, 'count' => $count]);
        exit;
    } catch (PDOException $exception) {
        // Fall through to the JSON file mode when the DB is not initialized.
    }
}

$likes = toolbox_like_file_counts();
$exists = false;
foreach (toolbox_all_sites() as $site) {
    if ($site['id'] === $id) {
        $exists = true;
        break;
    }
}
if (!$exists) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'site_not_found']);
    exit;
}

$likes[$id] = isset($likes[$id]) ? (int) $likes[$id] + 1 : 1;
file_put_contents(
    __DIR__ . '/data/likes.json',
    json_encode($likes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);
echo json_encode(['ok' => true, 'id' => $id, 'count' => $likes[$id]]);
