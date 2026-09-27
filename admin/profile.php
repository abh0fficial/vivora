<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $row = q_row('SELECT * FROM admins WHERE id = ?', [$admin['id']]);
    if (post('action') === 'profile') {
        $username = post('username');
        $email = post('email');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) $errors[] = 'Username must be 3–60 letters, numbers, dots, dashes or underscores.';
        elseif (q_val('SELECT id FROM admins WHERE username = ? AND id <> ?', [$username, $admin['id']])) $errors[] = 'That username is taken.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';
        if (!$errors) {
            q('UPDATE admins SET full_name = ?, username = ?, email = ? WHERE id = ?', [post('full_name'), $username, $email, $admin['id']]);
            log_activity('profile.update', 'Updated profile');
            flash('success', 'Profile updated.');
            redirect('admin/profile.php');
        }
    } elseif (post('action') === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $row['password_hash'])) $errors[] = 'Current password is incorrect.';
        elseif (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        elseif ($new !== ($_POST['confirm_password'] ?? '')) $errors[] = 'New passwords do not match.';
        if (!$errors) {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            log_activity('profile.password', 'Changed password');
            flash('success', 'Password changed.');
            redirect('admin/profile.php');
        }
    }
}
$usingDefault = password_verify('admin123', (string) q_val('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']]));

$pageTitle = 'My Profile';
$activeNav = 'profile';
$breadcrumbs = ['My Profile' => null];
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($usingDefault): ?>
    <div class="alert alert-warning"><i class="feather-alert-triangle me-2"></i>You are still using the default password <strong>admin123</strong>. Please change it below.</div>
<?php endif; ?>
<div class="row">
    <div class="col-xl-6">
        <form method="post" class="card stretch stretch-full">
            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <div class="card-header"><h5 class="card-title">Profile</h5></div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label">Full name</label><input name="full_name" class="form-control" value="<?= e($admin['full_name']) ?>" maxlength="120"></div>
                <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" value="<?= e($admin['username']) ?>" required maxlength="60"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($admin['email']) ?>" maxlength="160"></div>
                <p class="fs-12 text-muted mb-0">Last login: <?= e(fmt_date($admin['last_login_at'], 'd M Y, h:i A')) ?></p>
            </div>
            <div class="card-footer"><button class="btn btn-primary">Save profile</button></div>
        </form>
    </div>
    <div class="col-xl-6">
        <form method="post" class="card stretch stretch-full">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="card-header"><h5 class="card-title">Change password</h5></div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label">New password</label><input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password"></div>
                <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password"></div>
            </div>
            <div class="card-footer"><button class="btn btn-primary">Change password</button></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
