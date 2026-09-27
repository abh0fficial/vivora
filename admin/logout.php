<?php
require __DIR__ . '/../includes/auth.php';

require_post();
if (current_admin()) {
    log_activity('logout', 'Signed out');
}
logout_admin();
start_session();
flash('success', 'You have been logged out.');
redirect('admin/login.php');
