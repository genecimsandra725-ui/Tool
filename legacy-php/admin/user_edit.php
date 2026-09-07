<?php

require __DIR__ . '/_auth.php';

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user = null;
if ($editId > 0) {
    $stmt = $adminPdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$editId]);
    $user = $stmt->fetch();
}

$username = $user['username'] ?? '';
$nickname = $user['nickname'] ?? '';
$email = $user['email'] ?? '';
$role = $user['role'] ?? 'user';
$isActive = $user ? (int) $user['is_active'] : 1;

$adminTitle = $user ? '编辑用户：' . $user['username'] : '新增用户';
$adminCurrent = 'users';
require __DIR__ . '/_header.php';
?>
<a class="btn btn-small" href="users.php">← 返回用户列表</a>
<form class="admin-form" method="post" action="user_save.php">
    <input type="hidden" name="csrf_token" value="<?= e(toolbox_csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int) $editId ?>">
    <div class="form-grid">
        <label>
            <span>用户名 *</span>
            <input type="text" name="username" value="<?= e($username) ?>" required pattern="[A-Za-z0-9_]{3,32}">
        </label>
        <label>
            <span>角色</span>
            <select name="role">
                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>普通用户</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>管理员</option>
            </select>
        </label>
        <label>
            <span>昵称</span>
            <input type="text" name="nickname" value="<?= e($nickname) ?>" maxlength="60">
        </label>
        <label>
            <span>邮箱</span>
            <input type="email" name="email" value="<?= e($email) ?>">
        </label>
        <label>
            <span>新密码（留空表示不修改）</span>
            <input type="password" name="password" autocomplete="new-password">
        </label>
        <label>
            <span>确认新密码</span>
            <input type="password" name="password2" autocomplete="new-password">
        </label>
    </div>
    <label class="inline-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?>>
        <span>启用账号</span>
    </label>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit">保存用户</button>
    </div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
