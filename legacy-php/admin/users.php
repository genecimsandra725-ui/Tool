<?php

require __DIR__ . '/_auth.php';

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$sql = 'SELECT * FROM users WHERE 1 = 1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (username LIKE ? OR nickname LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY id DESC';
$stmt = $adminPdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$adminTitle = '用户管理';
$adminCurrent = 'users';
require __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form class="filter-form" method="get" action="users.php">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="搜索用户名、昵称、邮箱">
        <button type="submit">筛选</button>
    </form>
    <a class="btn btn-primary" href="user_edit.php">新增用户</a>
</div>

<section class="admin-section">
    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>账号</th>
            <th>角色</th>
            <th>状态</th>
            <th>注册时间</th>
            <th>最近登录</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= (int) $user['id'] ?></td>
                <td>
                    <strong><?= e($user['username']) ?></strong>
                    <div class="cell-sub"><?= e($user['nickname']) ?> <?= $user['email'] !== '' ? '· ' . e($user['email']) : '' ?></div>
                </td>
                <td><?= $user['role'] === 'admin' ? '管理员' : '普通用户' ?></td>
                <td><?= (int) $user['is_active'] === 1 ? '启用' : '停用' ?></td>
                <td><?= e($user['created_at']) ?></td>
                <td><?= e($user['last_login_at'] !== null ? $user['last_login_at'] : '-') ?></td>
                <td class="row-actions">
                    <a class="btn btn-small" href="user_edit.php?id=<?= (int) $user['id'] ?>">编辑</a>
                    <?php if ((int) $user['id'] !== (int) $adminUser['id']): ?>
                        <a class="btn btn-small btn-danger" href="user_delete.php?id=<?= (int) $user['id'] ?>&csrf=<?= e(toolbox_csrf_token()) ?>"
                           onclick="return confirm('确认删除该用户？')">删除</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
