<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_owner();

$editId = (int) get('edit');
$editing = $editId ? q_row('SELECT * FROM admins WHERE id = ?', [$editId]) : null;
$form = $editing ?: ['username' => '', 'full_name' => '', 'email' => '', 'role' => 'staff', 'is_active' => 1];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $id = (int) post('id');
    if (post('action') === 'delete') {
        if ($id === (int) $admin['id']) {
            flash('error', 'You cannot delete your own account.');
        } elseif ($u = q_row('SELECT * FROM admins WHERE id = ?', [$id])) {
            q('DELETE FROM admins WHERE id = ?', [$id]);
            log_activity('user.delete', 'Deleted user ' . $u['username']);
            flash('success', 'User deleted.');
        }
        redirect('admin/users.php');
    }
    $form = ['username' => post('username'), 'full_name' => post('full_name'), 'email' => post('email'),
        'role' => post('role') === 'admin' ? 'admin' : 'staff', 'is_active' => isset($_POST['is_active']) ? 1 : 0];
    $pw = (string) ($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $form['username'])) $errors[] = 'Username must be 3–60 letters, numbers, dots, dashes or underscores.';
    elseif (q_val('SELECT id FROM admins WHERE username = ? AND id <> ?', [$form['username'], $id])) $errors[] = 'That username is already taken.';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';
    if ((!$id || $pw !== '') && strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($id === (int) $admin['id'] && ($form['role'] !== 'admin' || !$form['is_active'])) $errors[] = 'You cannot remove your own administrator access.';
    if (!$errors) {
        if ($id) {
            q('UPDATE admins SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?', [$form['username'], $form['full_name'], $form['email'], $form['role'], $form['is_active'], $id]);
            if ($pw !== '') q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
            log_activity('user.update', 'Updated user ' . $form['username']);
            flash('success', 'User saved.');
        } else {
            q('INSERT INTO admins (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)',
                [$form['username'], password_hash($pw, PASSWORD_DEFAULT), $form['full_name'], $form['email'], $form['role'], $form['is_active']]);
            log_activity('user.create', 'Added ' . $form['role'] . ' user ' . $form['username']);
            flash('success', 'User added. They can log in with the username and password you set.');
        }
        redirect('admin/users.php');
    }
}
$users = q_all('SELECT id, username, full_name, email, role, is_active, last_login_at FROM admins ORDER BY role, username');

$pageTitle = 'Users & Roles';
$activeNav = 'users';
$breadcrumbs = ['Users' => null];
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Dashboard users</h5></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><span class="fw-semibold text-dark d-block"><?= e($u['full_name'] ?: $u['username']) ?></span><span class="fs-12 text-muted">@<?= e($u['username']) ?><?= $u['email'] ? ' · ' . e($u['email']) : '' ?></span></td>
                            <td><?= $u['role'] === 'admin' ? '<span class="badge bg-soft-primary text-primary">Administrator</span>' : '<span class="badge bg-gray-200 text-dark">Staff</span>' ?></td>
                            <td><?= $u['is_active'] ? '<span class="badge bg-soft-success text-success">Active</span>' : '<span class="badge bg-soft-danger text-danger">Disabled</span>' ?></td>
                            <td class="fs-12"><?= e(fmt_date($u['last_login_at'], 'd M Y, h:i A')) ?></td>
                            <td class="text-end"><div class="hstack gap-2 justify-content-end">
                                <a href="users.php?edit=<?= (int) $u['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a>
                                <?php if ((int) $u['id'] !== (int) $admin['id']): ?>
                                    <form method="post" class="m-0" data-confirm="Delete user <?= e($u['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button></form>
                                <?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card"><div class="card-body fs-13">
            <strong>Administrator</strong> — full access, including settings, users, backup, system check and deleting invoices.<br>
            <strong>Staff</strong> — can create and edit products, enquiries, quotations, invoices, payments, purchases, expenses and service tickets, but cannot change settings, manage users, take backups or delete invoices.
        </div></div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title"><?= $editing ? 'Edit user' : 'Add user' ?></h5></div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post" autocomplete="off">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                    <div class="mb-3"><label class="form-label">Full name</label><input name="full_name" class="form-control" value="<?= e($form['full_name']) ?>" maxlength="120"></div>
                    <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" value="<?= e($form['username']) ?>" required maxlength="60"></div>
                    <div class="mb-3"><label class="form-label">Email</label><input name="email" class="form-control" value="<?= e($form['email']) ?>" maxlength="160"></div>
                    <div class="mb-3"><label class="form-label"><?= $editing ? 'New password (leave blank to keep)' : 'Password' ?></label><input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" <?= $editing ? '' : 'required' ?>></div>
                    <div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-select"><option value="staff" <?= $form['role'] === 'staff' ? 'selected' : '' ?>>Staff</option><option value="admin" <?= $form['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option></select></div>
                    <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" id="u_active" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="u_active">Active (can log in)</label></div>
                    <div class="d-flex gap-2"><button class="btn btn-primary flex-fill"><?= $editing ? 'Save' : 'Add' ?> user</button><?php if ($editing): ?><a href="users.php" class="btn btn-light-brand">Cancel</a><?php endif; ?></div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
