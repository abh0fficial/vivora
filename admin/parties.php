<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$tab = in_array(get('tab'), ['customers', 'suppliers', 'collect', 'pay'], true) ? get('tab') : '';
$search = get('q');
$parties = [];
if ($tab !== 'suppliers' && $tab !== 'pay') {
    foreach (q_all("SELECT c.id, c.name, c.organization, c.phone, c.gstin, c.city,
            (SELECT COALESCE(SUM(grand_total),0) FROM invoices i WHERE i.customer_id = c.id AND i.status <> 'cancelled') billed,
            (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.customer_id = c.id AND p.direction = 'in') paid,
            (SELECT MAX(invoice_date) FROM invoices i WHERE i.customer_id = c.id) last_txn
        FROM customers c") as $r) {
        $parties[] = ['kind' => 'customer', 'id' => $r['id'], 'name' => $r['organization'] ?: $r['name'], 'sub' => $r['organization'] ? $r['name'] : '',
            'phone' => $r['phone'], 'gstin' => $r['gstin'], 'city' => $r['city'], 'balance' => round($r['billed'] - $r['paid'], 2), 'last' => $r['last_txn']];
    }
}
if ($tab !== 'customers' && $tab !== 'collect') {
    foreach (q_all("SELECT s.id, s.name, s.contact_person, s.phone, s.gstin, s.city,
            (SELECT COALESCE(SUM(grand_total),0) FROM purchases p WHERE p.supplier_id = s.id) billed,
            (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.supplier_id = s.id AND p.direction = 'out') paid,
            (SELECT MAX(bill_date) FROM purchases p WHERE p.supplier_id = s.id) last_txn
        FROM suppliers s") as $r) {
        $parties[] = ['kind' => 'supplier', 'id' => $r['id'], 'name' => $r['name'], 'sub' => $r['contact_person'],
            'phone' => $r['phone'], 'gstin' => $r['gstin'], 'city' => $r['city'], 'balance' => round($r['billed'] - $r['paid'], 2), 'last' => $r['last_txn']];
    }
}
if ($tab === 'collect') $parties = array_filter($parties, fn($p) => $p['balance'] > 0);
if ($tab === 'pay') $parties = array_filter($parties, fn($p) => $p['balance'] > 0);
if ($search !== '') {
    $needle = mb_strtolower($search);
    $parties = array_filter($parties, fn($p) => str_contains(mb_strtolower($p['name'] . ' ' . $p['sub'] . ' ' . $p['phone'] . ' ' . $p['gstin'] . ' ' . $p['city']), $needle));
}
usort($parties, fn($a, $b) => [abs($b['balance']) > 0, $b['last']] <=> [abs($a['balance']) > 0, $a['last']]);

$pageTitle = 'Parties';
$activeNav = 'parties';
$breadcrumbs = ['Parties' => null];
$pageActions = '<a href="customer-form.php" class="btn btn-primary"><i class="feather-user-plus me-2"></i>Add Customer</a><a href="suppliers.php" class="btn btn-light-brand"><i class="feather-truck me-2"></i>Add Supplier</a>';
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-6"><a href="parties.php?tab=collect" class="card stretch stretch-full text-reset border-success"><div class="card-body">
        <div class="fs-4 fw-bold text-success"><?= money(receivables_total()) ?></div><div class="fs-13 text-success fw-semibold">To Collect <i class="feather-arrow-down"></i></div>
    </div></a></div>
    <div class="col-6"><a href="parties.php?tab=pay" class="card stretch stretch-full text-reset border-danger"><div class="card-body">
        <div class="fs-4 fw-bold text-danger"><?= money(payables_total()) ?></div><div class="fs-13 text-danger fw-semibold">To Pay <i class="feather-arrow-up"></i></div>
    </div></a></div>
</div>
<div class="card">
    <div class="card-header flex-wrap gap-2">
        <ul class="nav nav-pills gap-1">
            <?php foreach (['' => 'All parties', 'customers' => 'Customers', 'suppliers' => 'Suppliers', 'collect' => 'To collect', 'pay' => 'To pay'] as $k => $v): ?>
                <li class="nav-item"><a class="nav-link py-1 px-3<?= $tab === $k ? ' active' : '' ?>" href="parties.php<?= $k ? '?tab=' . $k : '' ?>"><?= $v ?></a></li>
            <?php endforeach; ?>
        </ul>
        <form method="get" class="ms-auto d-flex gap-2"><?php if ($tab): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search name, phone, GSTIN" value="<?= e($search) ?>"><button class="btn btn-sm btn-primary"><i class="feather-search"></i></button></form>
    </div>
    <div class="card-body p-0">
        <?php if (!$parties): ?><div class="vh-empty"><i class="feather-users"></i>No parties found.</div><?php endif; ?>
        <div class="list-group list-group-flush">
            <?php foreach ($parties as $p): $isC = $p['kind'] === 'customer'; ?>
                <div class="list-group-item d-flex align-items-center gap-3 py-3 px-4">
                    <span class="avatar-text avatar-md <?= $isC ? 'bg-soft-primary text-primary' : 'bg-soft-warning text-warning' ?>"><?= e(strtoupper(mb_substr($p['name'], 0, 1))) ?></span>
                    <div class="flex-grow-1 min-w-0">
                        <a href="<?= $isC ? 'customer-ledger.php?id=' : 'supplier-ledger.php?id=' ?><?= (int) $p['id'] ?>" class="fw-semibold text-dark d-block text-truncate"><?= e($p['name']) ?></a>
                        <span class="fs-12 text-muted"><?= $isC ? 'Customer' : 'Supplier' ?><?= $p['phone'] ? ' · ' . e($p['phone']) : '' ?><?= $p['gstin'] ? ' · ' . e($p['gstin']) : '' ?></span>
                    </div>
                    <div class="text-end">
                        <?php if ($p['balance'] > 0): ?>
                            <div class="fw-bold <?= $isC ? 'text-success' : 'text-danger' ?>"><?= money($p['balance']) ?></div><div class="fs-11 <?= $isC ? 'text-success' : 'text-danger' ?>"><?= $isC ? 'To collect' : 'To pay' ?></div>
                        <?php elseif ($p['balance'] < 0): ?>
                            <div class="fw-bold text-info"><?= money(-$p['balance']) ?></div><div class="fs-11 text-info">Advance</div>
                        <?php else: ?><div class="fs-12 text-muted">Settled</div><?php endif; ?>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown"><i class="feather-more-vertical"></i></a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="<?= $isC ? 'customer-ledger.php?id=' : 'supplier-ledger.php?id=' ?><?= (int) $p['id'] ?>"><i class="feather-book-open me-2"></i>Statement / ledger</a>
                            <?php if ($isC): ?>
                                <a class="dropdown-item" href="invoice-form.php?customer_id=<?= (int) $p['id'] ?>"><i class="feather-file-plus me-2"></i>New invoice</a>
                                <a class="dropdown-item" href="payments.php?type=in&customer=<?= (int) $p['id'] ?>"><i class="feather-download me-2"></i>Receive payment</a>
                                <a class="dropdown-item" href="customer-form.php?id=<?= (int) $p['id'] ?>"><i class="feather-edit-3 me-2"></i>Edit</a>
                            <?php else: ?>
                                <a class="dropdown-item" href="purchase-form.php?supplier_id=<?= (int) $p['id'] ?>"><i class="feather-shopping-cart me-2"></i>New purchase bill</a>
                                <a class="dropdown-item" href="payments.php?type=out&supplier=<?= (int) $p['id'] ?>"><i class="feather-upload me-2"></i>Make payment</a>
                                <a class="dropdown-item" href="suppliers.php?edit=<?= (int) $p['id'] ?>"><i class="feather-edit-3 me-2"></i>Edit</a>
                            <?php endif; ?>
                            <?php if ($p['phone']): $wa = preg_replace('/\D+/', '', $p['phone']); if (strlen($wa) === 10) $wa = '91' . $wa; ?>
                                <a class="dropdown-item" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Dear ' . $p['name'] . ', ' . ($p['balance'] > 0 && $isC ? 'a balance of ' . money($p['balance']) . ' is pending with ' . setting('company_name', 'Vivora Healthcare') . '. Kindly arrange the payment.' : 'greetings from ' . setting('company_name', 'Vivora Healthcare') . '.')) ?>" target="_blank"><i class="feather-message-circle me-2"></i>WhatsApp<?= $p['balance'] > 0 && $isC ? ' reminder' : '' ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
