<?php
/**
 * Dashboard layout: <head>, sidebar and top header.
 * Expects: $pageTitle (string), $activeNav (string), optional $breadcrumbs (array label => url|null),
 * optional $pageActions (html for the right side of the page header), optional $extraCss (array of paths).
 */
$admin = current_admin();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$newEnquiries = (int) q_val("SELECT COUNT(*) FROM enquiries WHERE status = 'new'");
$openService = (int) q_val("SELECT COUNT(*) FROM service_requests WHERE status IN ('open','scheduled','in_progress')");
$lowStock = (int) q_val('SELECT COUNT(*) FROM products WHERE is_active = 1 AND min_stock > 0 AND stock_qty <= min_stock');
$notif = admin_notifications($admin);
$initials = strtoupper(substr($admin['full_name'] ?: $admin['username'], 0, 1));

$overdueInvoices = (int) q_val("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','partial') AND due_date < CURDATE()");
$owner = is_owner($admin);
$nav = [
    ['caption' => 'Overview'],
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'airplay', 'url' => 'index.php'],
    ['caption' => 'Billing'],
    ['key' => 'invoices', 'label' => 'Sales Invoices', 'icon' => 'file-text', 'badge' => $overdueInvoices, 'children' => [
        ['key' => 'invoices', 'label' => 'All Invoices', 'url' => 'invoices.php'],
        ['key' => 'invoice-new', 'label' => 'Create Invoice', 'url' => 'invoice-form.php'],
    ]],
    ['key' => 'payments-in', 'label' => 'Payments', 'icon' => 'credit-card', 'children' => [
        ['key' => 'payments-in', 'label' => 'Payments Received', 'url' => 'payments.php?type=in'],
        ['key' => 'payments-out', 'label' => 'Payments Made', 'url' => 'payments.php?type=out'],
    ]],
    ['key' => 'quotations', 'label' => 'Quotations', 'icon' => 'clipboard', 'children' => [
        ['key' => 'quotations', 'label' => 'All Quotations', 'url' => 'quotations.php'],
        ['key' => 'quotation-new', 'label' => 'Create Quotation', 'url' => 'quotation-form.php'],
    ]],
    ['key' => 'parties', 'label' => 'Parties', 'icon' => 'users', 'children' => [
        ['key' => 'parties', 'label' => 'All Parties', 'url' => 'parties.php'],
        ['key' => 'customers', 'label' => 'Customers', 'url' => 'customers.php'],
        ['key' => 'suppliers', 'label' => 'Suppliers', 'url' => 'suppliers.php'],
    ]],
    ['key' => 'cash-bank', 'label' => 'Cash & Bank', 'icon' => 'briefcase', 'url' => 'cash-bank.php'],
    ['caption' => 'Purchases & Expenses'],
    ['key' => 'purchases', 'label' => 'Purchases', 'icon' => 'shopping-cart', 'children' => [
        ['key' => 'purchases', 'label' => 'Purchase Bills', 'url' => 'purchases.php'],
        ['key' => 'purchase-new', 'label' => 'New Purchase Bill', 'url' => 'purchase-form.php'],
    ]],
    ['key' => 'expenses', 'label' => 'Expenses', 'icon' => 'dollar-sign', 'url' => 'expenses.php'],
    ['caption' => 'Catalogue & Stock'],
    ['key' => 'products', 'label' => 'Products', 'icon' => 'package', 'children' => [
        ['key' => 'products', 'label' => 'All Products', 'url' => 'products.php'],
        ['key' => 'product-new', 'label' => 'Add Product', 'url' => 'product-form.php'],
        ['key' => 'categories', 'label' => 'Categories', 'url' => 'categories.php'],
    ]],
    ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'layers', 'url' => 'inventory.php', 'badge' => $lowStock, 'badgeColor' => 'warning'],
    ['caption' => 'CRM & Service'],
    ['key' => 'enquiries', 'label' => 'Enquiries', 'icon' => 'inbox', 'badge' => $newEnquiries, 'children' => [
        ['key' => 'enquiries', 'label' => 'All Enquiries', 'url' => 'enquiries.php'],
        ['key' => 'enquiry-new', 'label' => 'Add Enquiry', 'url' => 'enquiry-form.php'],
    ]],
    ['key' => 'service', 'label' => 'Service Requests', 'icon' => 'tool', 'url' => 'service-requests.php', 'badge' => $openService, 'badgeColor' => 'danger'],
    ['caption' => 'Reports'],
    ['key' => 'reports-hub', 'label' => 'All Reports', 'icon' => 'pie-chart', 'url' => 'reports-hub.php'],
    ['key' => 'reports', 'label' => 'Business Reports', 'icon' => 'bar-chart-2', 'url' => 'reports.php'],
    ['caption' => 'Administration'],
    ['key' => 'settings', 'label' => 'Company Settings', 'icon' => 'settings', 'url' => 'settings.php', 'owner' => true],
    ['key' => 'users', 'label' => 'Users & Roles', 'icon' => 'user-check', 'url' => 'users.php', 'owner' => true],
    ['key' => 'backup', 'label' => 'Backup', 'icon' => 'download-cloud', 'url' => 'backup.php', 'owner' => true],
    ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'user', 'url' => 'profile.php'],
    ['key' => 'activity', 'label' => 'Activity Log', 'icon' => 'clock', 'url' => 'activity.php', 'owner' => true],
    ['key' => 'system', 'label' => 'System Check', 'icon' => 'database', 'url' => 'system.php', 'owner' => true],
];
$nav = array_values(array_filter($nav, fn($i) => $owner || empty($i['owner'])));

function nav_is_active(array $item, string $active): bool
{
    if (($item['key'] ?? '') === $active) {
        return true;
    }
    foreach ($item['children'] ?? [] as $c) {
        if ($c['key'] === $active) {
            return true;
        }
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> · <?= e(setting('company_name', 'Vivora Healthcare')) ?> Dashboard</title>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendors/css/vendors.min.css') ?>">
    <?php foreach ($extraCss ?? [] as $css): ?>
    <link rel="stylesheet" href="<?= asset($css) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= asset('css/theme.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <style>
        /* Phones & tablets: hide the page title / breadcrumb strip (kept inline so a cached stylesheet can't bring it back). */
        @media (max-width: 991.98px) {
            .nxl-content .page-header .page-header-left { display: none !important; }
            .nxl-content .page-header.vh-no-actions { display: none !important; }
            .nxl-content .page-header { padding: 12px 16px !important; height: auto !important; min-height: 0 !important; }
            .nxl-content .page-header .page-header-right { display: block !important; margin-left: 0 !important; width: 100%; }
        }
    </style>
    <script>try { localStorage.removeItem('app-skin-dark'); localStorage.removeItem('app-skin'); } catch (e) {}</script>
</head>
<body>
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="index.php" class="b-brand">
                <img src="<?= asset('images/logo-nav.png') ?>" alt="Vivora Healthcare" class="logo logo-lg vh-logo-light">
                <img src="<?= asset('images/logo-nav-white.png') ?>" alt="Vivora Healthcare" class="logo logo-lg vh-logo-dark">
                <img src="<?= asset('images/logo-mark.png') ?>" alt="Vivora" class="logo logo-sm">
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
                <?php foreach ($nav as $item): ?>
                    <?php if (isset($item['caption'])): ?>
                        <li class="nxl-item nxl-caption"><label><?= e($item['caption']) ?></label></li>
                    <?php elseif (!empty($item['children'])): ?>
                        <?php $isActive = nav_is_active($item, $activeNav); ?>
                        <li class="nxl-item nxl-hasmenu<?= $isActive ? ' active nxl-trigger' : '' ?>">
                            <a href="javascript:void(0);" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-<?= e($item['icon']) ?>"></i></span>
                                <span class="nxl-mtext"><?= e($item['label']) ?></span>
                                <?php if (!empty($item['badge'])): ?><span class="badge bg-<?= e($item['badgeColor'] ?? ($item['key'] === 'invoices' ? 'danger' : 'primary')) ?> ms-auto me-2 vh-nav-badge"><?= (int) $item['badge'] ?></span><?php endif; ?>
                                <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                <?php foreach ($item['children'] as $child): ?>
                                    <li class="nxl-item<?= $child['key'] === $activeNav ? ' active' : '' ?>"><a class="nxl-link" href="<?= e($child['url']) ?>"><?= e($child['label']) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nxl-item<?= $item['key'] === $activeNav ? ' active' : '' ?>">
                            <a href="<?= e($item['url']) ?>" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-<?= e($item['icon']) ?>"></i></span>
                                <span class="nxl-mtext"><?= e($item['label']) ?></span>
                                <?php if (!empty($item['badge'])): ?>
                                    <span class="badge bg-<?= e($item['badgeColor']) ?> ms-auto vh-nav-badge"><?= (int) $item['badge'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</nav>

<header class="nxl-header">
    <div class="header-wrapper">
        <div class="header-left d-flex align-items-center gap-4">
            <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box"><div class="hamburger-inner"></div></div>
                </div>
            </a>
            <div class="nxl-navigation-toggle">
                <a href="javascript:void(0);" id="menu-mini-button"><i class="feather-align-left"></i></a>
                <a href="javascript:void(0);" id="menu-expend-button" style="display: none"><i class="feather-arrow-right"></i></a>
            </div>
            <form action="products.php" method="get" class="d-none d-md-flex vh-header-search">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="feather-search"></i></span>
                    <input type="search" name="q" class="form-control border-start-0" placeholder="Search products, SKU, brand…" value="<?= e(get('q')) ?>">
                </div>
            </form>
        </div>
        <div class="header-right ms-auto">
            <div class="d-flex align-items-center">
                <div class="nxl-h-item d-none d-sm-flex">
                    <div class="full-screen-switcher">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                            <i class="feather-maximize maximize"></i>
                            <i class="feather-minimize minimize"></i>
                        </a>
                    </div>
                </div>
                <div class="dropdown nxl-h-item">
                    <a class="nxl-head-link me-3 position-relative" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside" aria-label="Notifications">
                        <i class="feather-bell"></i>
                        <span class="badge bg-danger nxl-h-badge" id="vhNotifBadge"<?= $notif['unread'] ? '' : ' style="display:none"' ?>><?= $notif['unread'] > 99 ? '99+' : $notif['unread'] ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu vh-notif-menu">
                        <div class="d-flex justify-content-between align-items-center notifications-head">
                            <h6 class="fw-bold text-dark mb-0">Notifications<?= $notif['unread'] ? ' <span class="badge bg-soft-danger text-danger ms-1">' . $notif['unread'] . ' new</span>' : '' ?></h6>
                            <?php if ($notif['unread']): ?>
                                <form action="notifications.php" method="post" class="m-0"><?= csrf_field() ?>
                                    <button class="btn btn-link p-0 fs-11 text-success text-decoration-none"><i class="feather-check me-1"></i>Mark all as read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="vh-notif-list">
                            <?php if (!$notif['items']): ?>
                                <div class="text-center text-muted py-5 px-3"><i class="feather-bell-off fs-3 d-block mb-2"></i>You're all caught up.</div>
                            <?php endif; ?>
                            <?php foreach ($notif['items'] as $n): ?>
                                <a href="<?= e($n['url']) ?>" class="notifications-item text-reset<?= $n['unread'] ? ' vh-unread' : '' ?>">
                                    <div class="avatar-text avatar-md bg-soft-<?= e($n['color']) ?> text-<?= e($n['color']) ?> me-3 flex-shrink-0"><i class="feather-<?= e($n['icon']) ?>"></i></div>
                                    <div class="notifications-desc flex-grow-1 min-w-0">
                                        <span class="fw-semibold text-dark d-block text-truncate-1-line"><?= e($n['title']) ?></span>
                                        <?php if ($n['text'] !== ''): ?><span class="fs-12 text-muted d-block text-truncate-1-line"><?= e($n['text']) ?></span><?php endif; ?>
                                        <span class="notifications-date fs-11 text-muted"><?= e(time_ago($n['time'])) ?></span>
                                    </div>
                                    <?php if ($n['unread']): ?><span class="vh-unread-dot" title="Unread"></span><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center notifications-footer border-top py-2">
                            <a href="invoices.php?status=overdue" class="fs-12 fw-semibold">Overdue</a>
                            <span class="text-muted mx-2">·</span>
                            <a href="enquiries.php?status=new" class="fs-12 fw-semibold">Enquiries</a>
                            <span class="text-muted mx-2">·</span>
                            <a href="service-requests.php?status=open" class="fs-12 fw-semibold">Service</a>
                            <span class="text-muted mx-2">·</span>
                            <a href="inventory.php?filter=attention" class="fs-12 fw-semibold">Stock</a>
                        </div>
                    </div>
                </div>
                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <span class="avatar-text avatar-md bg-primary text-white user-avtar me-0"><?= e($initials) ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                        <div class="dropdown-header">
                            <div class="d-flex align-items-center">
                                <span class="avatar-text avatar-md bg-primary text-white me-3"><?= e($initials) ?></span>
                                <div>
                                    <h6 class="text-dark mb-0"><?= e($admin['full_name'] ?: $admin['username']) ?> <span class="badge bg-soft-success text-success ms-1"><?= $owner ? 'Admin' : 'Staff' ?></span></h6>
                                    <span class="fs-12 fw-medium text-muted">@<?= e($admin['username']) ?></span>
                                </div>
                            </div>
                        </div>
                        <a href="profile.php" class="dropdown-item"><i class="feather-user"></i><span>My Profile</span></a>
                        <?php if ($owner): ?><a href="settings.php" class="dropdown-item"><i class="feather-settings"></i><span>Company Settings</span></a><?php endif; ?>
                        <?php if ($owner): ?><a href="activity.php" class="dropdown-item"><i class="feather-activity"></i><span>Activity Log</span></a><a href="backup.php" class="dropdown-item"><i class="feather-download-cloud"></i><span>Backup</span></a><?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <form action="logout.php" method="post" class="m-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item"><i class="feather-log-out"></i><span>Logout</span></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header<?= empty($pageActions) || $activeNav === 'dashboard' ? ' vh-no-actions' : '' ?>">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10"><?= e($pageTitle) ?></h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <?php foreach ($breadcrumbs as $label => $url): ?>
                        <li class="breadcrumb-item"><?= $url ? '<a href="' . e($url) . '">' . e($label) . '</a>' : e($label) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if (!empty($pageActions)): ?>
                <div class="page-header-right ms-auto">
                    <div class="d-flex align-items-center gap-2 flex-wrap"><?= $pageActions ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="main-content">
            <?= render_flashes() ?>
