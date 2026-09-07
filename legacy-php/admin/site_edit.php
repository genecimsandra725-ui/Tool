<?php

require __DIR__ . '/_auth.php';

$editId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$site = null;
if ($editId !== '') {
    $stmt = $adminPdo->prepare('SELECT * FROM sites WHERE id = ?');
    $stmt->execute([$editId]);
    $site = $stmt->fetch();
}

$categories = $adminPdo->query(
    'SELECT slug, name FROM categories ORDER BY sort_order'
)->fetchAll();

$linksText = '';
if ($site && !empty($site['extra_links'])) {
    $links = json_decode($site['extra_links'], true);
    if (is_array($links)) {
        $lines = [];
        foreach ($links as $link) {
            $lines[] = (isset($link['label']) ? $link['label'] : '') . '|' . (isset($link['url']) ? $link['url'] : '');
        }
        $linksText = implode("\n", $lines);
    }
}

$name = $site['name'] ?? '';
$category = $site['category'] ?? ($categories[0]['slug'] ?? 'tool');
$description = $site['description'] ?? '';
$url = $site['url'] ?? '';
$isActive = $site ? (int) $site['is_active'] : 1;
$sortOrder = $site ? (int) $site['sort_order'] : 0;

$adminTitle = $site ? '编辑站点：' . $site['name'] : '新增站点';
$adminCurrent = 'sites';
require __DIR__ . '/_header.php';
?>
<a class="btn btn-small" href="sites.php">← 返回站点列表</a>
<form class="admin-form" method="post" action="site_save.php">
    <input type="hidden" name="csrf_token" value="<?= e(toolbox_csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e($editId) ?>">
    <div class="form-grid">
        <label>
            <span>站点名称 *</span>
            <input type="text" name="name" value="<?= e($name) ?>" required maxlength="255">
        </label>
        <label>
            <span>分类 *</span>
            <select name="category">
                <?php foreach ($categories as $row): ?>
                    <option value="<?= e($row['slug']) ?>" <?= $category === $row['slug'] ? 'selected' : '' ?>>
                        <?= e($row['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="span-2">
            <span>访问地址 *</span>
            <input type="url" name="url" value="<?= e($url) ?>" required>
        </label>
        <label class="span-2">
            <span>简介</span>
            <textarea name="description" rows="3"><?= e($description) ?></textarea>
        </label>
        <label>
            <span>排序值</span>
            <input type="number" name="sort_order" value="<?= (int) $sortOrder ?>">
        </label>
        <label>
            <span>备用链接（每行：标签|网址）</span>
            <textarea name="links" rows="5" placeholder="源码|https://github.com/example/repo"><?= e($linksText) ?></textarea>
        </label>
    </div>
    <label class="inline-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?>>
        <span>前台显示</span>
    </label>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit">保存站点</button>
    </div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
