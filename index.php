<?php
/** The site is dashboard-only: send visitors to the admin area (or the installer on first run). */
require __DIR__ . '/includes/bootstrap.php';
redirect('admin/index.php');
