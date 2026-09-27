<?php
require __DIR__ . '/../includes/auth.php';

if (current_admin()) {
    redirect('admin/index.php');
}

$error = '';
$username = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $username = post('username');
    $password = (string) ($_POST['password'] ?? '');
    if (login_throttled()) {
        $error = 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } elseif (attempt_login($username, $password)) {
        $next = get('next');
        // Only allow local redirects back into the dashboard.
        if ($next !== '' && preg_match('~^/[^/\\\\]~', $next) && strpos($next, '/admin/') !== false) {
            redirect($next);
        }
        redirect('admin/index.php');
    } else {
        $error = 'Invalid username or password.';
    }
}
header('X-Robots-Tag: noindex, nofollow');
$company = setting('company_name', 'Vivora Healthcare');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Login · <?= e($company) ?> Dashboard</title>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendors/css/vendors.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/theme.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>?v=<?= APP_VERSION ?>">
    <style>
        .auth-cover-content-inner { background: linear-gradient(135deg, #eaf2fa 0%, #e6f6f5 100%); }
        .vh-login-tagline { color: var(--vh-navy); font-weight: 600; }
    </style>
</head>
<body>
<main class="auth-cover-wrapper">
    <div class="auth-cover-content-inner">
        <div class="auth-cover-content-wrapper">
            <div class="auth-img text-center">
                <img src="<?= asset('images/login-bg.svg') ?>" alt="" class="img-fluid">
                <p class="vh-login-tagline fs-5 mt-4"><?= e(setting('tagline', 'Better Equipment. Healthier Tomorrows.')) ?></p>
            </div>
        </div>
    </div>
    <div class="auth-cover-sidebar-inner">
        <div class="auth-cover-card-wrapper">
            <div class="auth-cover-card p-sm-5">
                <div class="mb-5">
                    <a href="<?= base_url('index.php') ?>"><img src="<?= asset('images/logo-full.png') ?>" alt="<?= e($company) ?>" class="img-fluid" style="max-width:260px"></a>
                </div>
                <h2 class="fs-20 fw-bolder mb-4">Dashboard Login</h2>
                <h4 class="fs-13 fw-bold mb-2">Sign in to manage your business</h4>
                <p class="fs-12 fw-medium text-muted">Products, enquiries, quotations, customers and service requests — all in one place.</p>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2 fs-12 mt-3"><?= e($error) ?></div>
                <?php endif; ?>
                <?= render_flashes() ?>
                <form method="post" class="w-100 mt-4 pt-2" autocomplete="on">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Username" value="<?= e($username) ?>" required autofocus autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
                            <button class="btn btn-light-brand" type="button" id="togglePw" aria-label="Show password"><i class="feather-eye"></i></button>
                        </div>
                    </div>
                    <div class="mt-5">
                        <button type="submit" class="btn btn-lg btn-primary w-100">Login</button>
                    </div>
                </form>
                <div class="mt-5 text-muted fs-12">
                    <a href="<?= base_url('index.php') ?>" class="fw-semibold"><i class="feather-arrow-left me-1"></i>Back to website</a>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
    document.getElementById('togglePw').addEventListener('click', function () {
        var i = document.getElementById('password');
        i.type = i.type === 'password' ? 'text' : 'password';
        this.innerHTML = i.type === 'password' ? '<i class="feather-eye"></i>' : '<i class="feather-eye-off"></i>';
    });
</script>
</body>
</html>
