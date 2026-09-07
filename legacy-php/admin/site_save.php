<?php

require __DIR__ . '/_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    toolbox_redirect('sites.php');
}
if (!toolbox_csrf_check()) {
    exit('CSRF 校验失败');
}

$id = isset($_POST['id']) ? trim((string) $_POST['id']) : '';
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$category = isset($_POST['category']) ? trim((string) $_POST['category']) : '';
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
$url = isset($_POST['url']) ? trim((string) $_POST['url']) : '';
$linksInput = isset($_POST['links']) ? (string) $_POST['links'] : '';
$isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
$sortOrder = isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : 0;

if ($name === '' || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
    exit('站点名称与合法访问地址为必填项');
}

$categoryRows = $adminPdo->query('SELECT slug FROM categories')->fetchAll();
$validCategories = array_column($categoryRows, 'slug');
if (!in_array($category, $validCategories, true)) {
    exit('分类不存在');
}

$links = [];
foreach (preg_split('/\r\n|\r|\n/', $linksInput) as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $parts = explode('|', $line, 2);
    if (count($parts) === 2) {
        $label = trim($parts[0]);
        $linkUrl = trim($parts[1]);
    } else {
        $label = '';
        $linkUrl = trim($parts[0]);
    }
    if ($linkUrl !== '' && filter_var($linkUrl, FILTER_VALIDATE_URL) !== false) {
        $links[] = ['label' => $label, 'url' => $linkUrl];
    }
}

if ($id === '') {
    $max = (int) $adminPdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(id, '-', -1) AS UNSIGNED)), 0)
         FROM sites"
    )->fetchColumn();
    $id = 'am-' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    $stmt = $adminPdo->prepare(
        'INSERT INTO sites
            (id, category, name, description, url, extra_links, sort_order, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id,
        $category,
        $name,
        $description,
        $url,
        json_encode($links, JSON_UNESCAPED_UNICODE),
        $sortOrder,
        $isActive,
    ]);
} else {
    $stmt = $adminPdo->prepare(
        'UPDATE sites
         SET category = ?, name = ?, description = ?, url = ?,
             extra_links = ?, sort_order = ?, is_active = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $category,
        $name,
        $description,
        $url,
        json_encode($links, JSON_UNESCAPED_UNICODE),
        $sortOrder,
        $isActive,
        $id,
    ]);
}

toolbox_sync_sites_json();
toolbox_redirect('sites.php?category=' . urlencode($category));
