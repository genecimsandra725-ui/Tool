<?php

// 初始化种子数据：先执行 install/database.sql，再运行本文件。
// 命令行用法：php install/seed.php

require __DIR__ . '/../includes/functions.php';

$pdo = toolbox_db_required();
$config = toolbox_config();

try {
    $pdo->beginTransaction();

    $categoryStmt = $pdo->prepare(
        'INSERT INTO categories (slug, name, icon, description, sort_order)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            icon = VALUES(icon),
            description = VALUES(description),
            sort_order = VALUES(sort_order)'
    );
    foreach ($config['categories'] as $index => $category) {
        $categoryStmt->execute([
            $category['slug'],
            $category['name'],
            $category['icon'],
            $category['desc'],
            $index + 1,
        ]);
    }

    $sites = toolbox_json_sites(false);
    $likes = toolbox_like_file_counts();
    $siteStmt = $pdo->prepare(
        'INSERT INTO sites
            (id, category, name, description, url, extra_links, sort_order, is_active, like_count)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE
            category = VALUES(category),
            name = VALUES(name),
            description = VALUES(description),
            url = VALUES(url),
            extra_links = VALUES(extra_links),
            sort_order = VALUES(sort_order),
            is_active = 1,
            like_count = VALUES(like_count)'
    );

    foreach ($sites as $index => $site) {
        $siteId = isset($site['id']) ? $site['id'] : 'site-' . ($index + 1);
        $likeCount = isset($likes[$siteId]) ? (int) $likes[$siteId] : 0;
        $links = isset($site['links']) && is_array($site['links'])
            ? $site['links']
            : [];
        $extraLinks = json_encode($links, JSON_UNESCAPED_UNICODE);
        $siteStmt->execute([
            $siteId,
            isset($site['category']) ? $site['category'] : 'tool',
            isset($site['name']) ? $site['name'] : '',
            isset($site['desc']) ? $site['desc'] : '',
            isset($site['url']) ? $site['url'] : '',
            $extraLinks !== false ? $extraLinks : '[]',
            $index + 1,
            $likeCount,
        ]);
    }

    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $adminStmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, nickname, email, role, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $adminStmt->execute([
            'admin',
            password_hash('admin123', PASSWORD_DEFAULT),
            '管理员',
            '',
            'admin',
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}

echo "seed done\n";
echo 'sites: ' . $pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn() . "\n";
echo 'categories: ' . $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() . "\n";
echo 'admin: admin / admin123' . "\n";
