<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$accId = (int) get('account');
$editId = (int) get('edit');
$editing = $editId ? q_row('SELECT * FROM accounts WHERE id = ?', [$editId]) : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $action = post('action');
    if ($action === 'save_account') {
        if (!is_owner($admin)) {
            flash('error', 'Only an administrator can add or edit accounts.');
            redirect('admin/cash-bank.php');
        }
        $d = [
            'name' => mb_substr(post('name'), 0, 120), 'type' => post('type') === 'cash' ? 'cash' : 'bank',
            'bank_name' => mb_substr(post('bank_name'), 0, 120), 'account_no' => mb_substr(post('account_no'), 0, 40),
            'ifsc' => strtoupper(mb_substr(post('ifsc'), 0, 20)), 'upi_id' => mb_substr(post('upi_id'), 0, 80),
            'opening_balance' => round((float) post('opening_balance'), 2),
            'opening_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', post('opening_date')) ? post('opening_date') : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($d['name'] === '') {
            flash('error', 'Account name is required.');
        } else {
            $cols = array_keys($d);
            if ($id = (int) post('id')) {
                q('UPDATE accounts SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', array_merge(array_values($d), [$id]));
            } else {
                q('INSERT INTO accounts (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($d));
                $id = (int) db()->lastInsertId();
            }
            if (isset($_POST['is_default'])) {
                q('UPDATE accounts SET is_default = (id = ?) WHERE type = ?', [$id, $d['type']]);
            }
            log_activity('account.save', 'Saved account "' . $d['name'] . '"');
            flash('success', 'Account saved.');
        }
        redirect('admin/cash-bank.php');
    }
    if ($action === 'adjust') {
        $type = post('type');
        $amount = round((float) post('amount'), 2);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('txn_date')) ? post('txn_date') : date('Y-m-d');
        $from = (int) post('account_id');
        $to = (int) post('to_account_id');
        $note = mb_substr(post('note'), 0, 300);
        if ($amount <= 0 || !q_val('SELECT id FROM accounts WHERE id = ?', [$from])) {
            flash('error', 'Choose an account and enter an amount.');
        } elseif ($type === 'transfer') {
            if ($to === $from || !q_val('SELECT id FROM accounts WHERE id = ?', [$to])) {
                flash('error', 'Choose two different accounts for a transfer.');
            } else {
                $grp = bin2hex(random_bytes(6));
                q("INSERT INTO account_txns (account_id, txn_date, type, amount, note, transfer_group) VALUES (?, ?, 'transfer_out', ?, ?, ?), (?, ?, 'transfer_in', ?, ?, ?)",
                    [$from, $date, $amount, $note, $grp, $to, $date, $amount, $note, $grp]);
                log_activity('account.transfer', 'Transferred ' . money($amount) . ' between accounts');
                flash('success', money($amount) . ' transferred.');
            }
        } elseif (in_array($type, ['add', 'reduce'], true)) {
            q('INSERT INTO account_txns (account_id, txn_date, type, amount, note) VALUES (?, ?, ?, ?, ?)', [$from, $date, $type, $amount, $note]);
            log_activity('account.adjust', ($type === 'add' ? 'Added ' : 'Reduced ') . money($amount) . ($note ? " ($note)" : ''));
            flash('success', 'Balance ' . ($type === 'add' ? 'increased' : 'reduced') . ' by ' . money($amount) . '.');
        }
        redirect('admin/cash-bank.php' . ($from ? '?account=' . $from : ''));
    }
    if ($action === 'delete_txn') {
        $t = q_row('SELECT * FROM account_txns WHERE id = ?', [(int) post('id')]);
        if ($t) {
            if ($t['transfer_group'] !== '') q('DELETE FROM account_txns WHERE transfer_group = ?', [$t['transfer_group']]);
            else q('DELETE FROM account_txns WHERE id = ?', [$t['id']]);
            flash('success', 'Entry deleted.');
        }
        redirect('admin/cash-bank.php?account=' . (int) ($t['account_id'] ?? 0));
    }
}

$balances = account_balances();
$total = array_sum(array_map(fn($a) => $a['is_active'] ? $a['balance'] : 0, $balances));
$cashTotal = array_sum(array_map(fn($a) => $a['type'] === 'cash' ? $a['balance'] : 0, $balances));
$current = $accId && isset($balances[$accId]) ? $balances[$accId] : null;

// Statement for the selected account.
$rows = [];
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : date('Y-m-d');
if ($current) {
    $opening = account_balances(date('Y-m-d', strtotime($from . ' -1 day')))[$accId]['balance'];
    foreach (q_all("SELECT p.id, p.payment_date d, p.direction, p.amount, p.party_name, p.receipt_no, p.reference, p.mode, i.invoice_no, pu.bill_no FROM payments p
        LEFT JOIN invoices i ON i.id = p.invoice_id LEFT JOIN purchases pu ON pu.id = p.purchase_id WHERE p.account_id = ? AND p.payment_date BETWEEN ? AND ?", [$accId, $from, $to]) as $r) {
        $rows[] = ['d' => $r['d'], 'type' => $r['direction'] === 'in' ? 'Payment received' : 'Payment made', 'party' => $r['party_name'],
            'ref' => $r['direction'] === 'in' ? $r['receipt_no'] . ($r['invoice_no'] ? ' → ' . $r['invoice_no'] : '') : ($r['bill_no'] ? 'Bill ' . $r['bill_no'] : $r['reference']),
            'in' => $r['direction'] === 'in' ? (float) $r['amount'] : 0, 'out' => $r['direction'] === 'out' ? (float) $r['amount'] : 0, 'txn' => null];
    }
    foreach (q_all('SELECT expense_date d, category, description, paid_to, amount FROM expenses WHERE account_id = ? AND expense_date BETWEEN ? AND ?', [$accId, $from, $to]) as $r) {
        $rows[] = ['d' => $r['d'], 'type' => 'Expense – ' . $r['category'], 'party' => $r['paid_to'], 'ref' => $r['description'], 'in' => 0, 'out' => (float) $r['amount'], 'txn' => null];
    }
    $labels = ['add' => 'Money added', 'reduce' => 'Money withdrawn', 'transfer_in' => 'Transfer in', 'transfer_out' => 'Transfer out'];
    foreach (q_all('SELECT * FROM account_txns WHERE account_id = ? AND txn_date BETWEEN ? AND ?', [$accId, $from, $to]) as $r) {
        $plus = in_array($r['type'], ['add', 'transfer_in'], true);
        $rows[] = ['d' => $r['txn_date'], 'type' => $labels[$r['type']], 'party' => '', 'ref' => $r['note'], 'in' => $plus ? (float) $r['amount'] : 0, 'out' => $plus ? 0 : (float) $r['amount'], 'txn' => (int) $r['id']];
    }
    usort($rows, fn($a, $b) => strcmp($a['d'], $b['d']));
}

$pageTitle = 'Cash & Bank';
$activeNav = 'cash-bank';
$breadcrumbs = ['Cash & Bank' => null];
require __DIR__ . '/partials/header.php';
$accountsActive = cash_bank_accounts();
?>
<div class="row">
    <div class="col-md-4"><div class="card stretch stretch-full"><div class="card-body d-flex align-items-center gap-3">
        <span class="avatar-text avatar-lg bg-soft-primary text-primary"><i class="feather-briefcase"></i></span>
        <div><div class="fs-4 fw-bold text-dark"><?= money($total) ?></div><div class="fs-12 text-muted">Total balance (cash + bank)</div></div>
    </div></div></div>
    <div class="col-md-4"><div class="card stretch stretch-full"><div class="card-body d-flex align-items-center gap-3">
        <span class="avatar-text avatar-lg bg-soft-success text-success"><i class="feather-dollar-sign"></i></span>
        <div><div class="fs-4 fw-bold text-dark"><?= money($cashTotal) ?></div><div class="fs-12 text-muted">Cash in hand</div></div>
    </div></div></div>
    <div class="col-md-4"><div class="card stretch stretch-full"><div class="card-body d-flex align-items-center gap-3">
        <span class="avatar-text avatar-lg bg-soft-info text-info"><i class="feather-credit-card"></i></span>
        <div><div class="fs-4 fw-bold text-dark"><?= money($total - $cashTotal) ?></div><div class="fs-12 text-muted">In bank accounts</div></div>
    </div></div></div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Accounts</h5></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Account</th><th>Details</th><th class="text-end">Balance</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($balances as $a): ?>
                        <tr class="<?= $a['is_active'] ? '' : 'opacity-50' ?><?= $accId === (int) $a['id'] ? ' bg-gray-100' : '' ?>">
                            <td><a href="cash-bank.php?account=<?= (int) $a['id'] ?>" class="fw-semibold text-dark"><i class="feather-<?= $a['type'] === 'cash' ? 'dollar-sign' : 'credit-card' ?> me-2 text-primary"></i><?= e($a['name']) ?></a>
                                <?= $a['is_default'] ? '<span class="badge bg-soft-primary text-primary ms-1">Default ' . e($a['type']) . '</span>' : '' ?><?= $a['is_active'] ? '' : '<span class="badge bg-gray-200 text-dark ms-1">Inactive</span>' ?></td>
                            <td class="fs-12 text-muted"><?= e(implode(' · ', array_filter([$a['bank_name'], $a['account_no'] ? 'A/c ' . $a['account_no'] : '', $a['ifsc'], $a['upi_id']]))) ?: '—' ?></td>
                            <td class="text-end fw-bold <?= $a['balance'] < 0 ? 'text-danger' : 'text-dark' ?>"><?= money($a['balance']) ?></td>
                            <td class="text-end"><div class="hstack gap-2 justify-content-end">
                                <a href="cash-bank.php?account=<?= (int) $a['id'] ?>" class="avatar-text avatar-md" title="Statement"><i class="feather-list"></i></a>
                                <?php if (is_owner($admin)): ?><a href="cash-bank.php?edit=<?= (int) $a['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a><?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($current): $bal = $opening; ?>
        <div class="card">
            <div class="card-header flex-wrap gap-2">
                <h5 class="card-title">Statement · <?= e($current['name']) ?></h5>
                <form method="get" class="ms-auto d-flex gap-2 flex-wrap"><input type="hidden" name="account" value="<?= $accId ?>">
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"><button class="btn btn-sm btn-primary">Go</button></form>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover mb-0 fs-13">
                    <thead><tr><th>Date</th><th>Type</th><th>Party / details</th><th class="text-end">Money in</th><th class="text-end">Money out</th><th class="text-end">Balance</th><th></th></tr></thead>
                    <tbody>
                        <tr class="fw-semibold"><td><?= e(fmt_date($from)) ?></td><td colspan="4">Opening balance</td><td class="text-end"><?= money($opening) ?></td><td></td></tr>
                        <?php foreach ($rows as $r): $bal += $r['in'] - $r['out']; ?>
                            <tr>
                                <td class="text-nowrap"><?= e(fmt_date($r['d'])) ?></td><td><?= e($r['type']) ?></td>
                                <td><?= e(trim($r['party'] . ($r['party'] && $r['ref'] ? ' · ' : '') . $r['ref'])) ?></td>
                                <td class="text-end text-success"><?= $r['in'] ? money($r['in']) : '' ?></td>
                                <td class="text-end text-danger"><?= $r['out'] ? money($r['out']) : '' ?></td>
                                <td class="text-end fw-semibold"><?= money($bal) ?></td>
                                <td class="text-end"><?php if ($r['txn']): ?><form method="post" class="m-0" data-confirm="Delete this entry?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_txn"><input type="hidden" name="id" value="<?= $r['txn'] ?>"><button class="btn btn-link p-0 text-danger" title="Delete"><i class="feather-x"></i></button></form><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No transactions in this period.</td></tr><?php endif; ?>
                    </tbody>
                    <tfoot class="fw-bold"><tr><td colspan="5" class="text-end">Closing balance</td><td class="text-end"><?= money($bal) ?></td><td></td></tr></tfoot>
                </table>
            </div>
        </div>
        <?php else: ?>
            <div class="card"><div class="card-body text-muted fs-13"><i class="feather-info me-1"></i>Click an account to see its statement. Payments received and made, and expenses, are linked to the account you choose on each form (cash → Cash in Hand, UPI / bank / cheque → your default bank account).</div></div>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Adjust balance / transfer</h5></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="adjust">
                    <div class="mb-3"><label class="form-label">Type</label>
                        <select name="type" id="adjType" class="form-select">
                            <option value="add">Add money (deposit / capital)</option>
                            <option value="reduce">Reduce money (withdrawal / drawings)</option>
                            <option value="transfer">Transfer between accounts</option>
                        </select></div>
                    <div class="mb-3"><label class="form-label" id="accLabel">Account</label>
                        <select name="account_id" class="form-select"><?php foreach ($accountsActive as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $accId === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3" id="toWrap" style="display:none"><label class="form-label">To account</label>
                        <select name="to_account_id" class="form-select"><?php foreach ($accountsActive as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Amount (₹)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
                        <div class="col-6 mb-3"><label class="form-label">Date</label><input type="date" name="txn_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Note</label><input name="note" class="form-control" maxlength="300" placeholder="e.g. Cash deposited in bank"></div>
                    <button class="btn btn-primary w-100">Save</button>
                </form>
            </div>
        </div>
        <?php if (is_owner($admin)): $f = $editing ?: ['id' => 0, 'name' => '', 'type' => 'bank', 'bank_name' => '', 'account_no' => '', 'ifsc' => '', 'upi_id' => '', 'opening_balance' => 0, 'opening_date' => '', 'is_default' => 0, 'is_active' => 1]; ?>
        <div class="card">
            <div class="card-header"><h5 class="card-title"><?= $editing ? 'Edit account' : 'Add bank / cash account' ?></h5></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="save_account"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                    <div class="row">
                        <div class="col-7 mb-3"><label class="form-label">Account name</label><input name="name" class="form-control" value="<?= e($f['name']) ?>" required maxlength="120" placeholder="e.g. HDFC Current A/c"></div>
                        <div class="col-5 mb-3"><label class="form-label">Type</label><select name="type" class="form-select"><option value="bank" <?= $f['type'] === 'bank' ? 'selected' : '' ?>>Bank</option><option value="cash" <?= $f['type'] === 'cash' ? 'selected' : '' ?>>Cash</option></select></div>
                        <div class="col-6 mb-3"><label class="form-label">Bank name</label><input name="bank_name" class="form-control" value="<?= e($f['bank_name']) ?>" maxlength="120"></div>
                        <div class="col-6 mb-3"><label class="form-label">Account no.</label><input name="account_no" class="form-control" value="<?= e($f['account_no']) ?>" maxlength="40"></div>
                        <div class="col-6 mb-3"><label class="form-label">IFSC</label><input name="ifsc" class="form-control text-uppercase" value="<?= e($f['ifsc']) ?>" maxlength="20"></div>
                        <div class="col-6 mb-3"><label class="form-label">UPI ID</label><input name="upi_id" class="form-control" value="<?= e($f['upi_id']) ?>" maxlength="80"></div>
                        <div class="col-6 mb-3"><label class="form-label">Opening balance (₹)</label><input type="number" step="0.01" name="opening_balance" class="form-control" value="<?= e((float) $f['opening_balance']) ?>"></div>
                        <div class="col-6 mb-3"><label class="form-label">As of date</label><input type="date" name="opening_date" class="form-control" value="<?= e($f['opening_date']) ?>"></div>
                    </div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="acc_def" name="is_default" <?= $f['is_default'] ? 'checked' : '' ?>><label class="form-check-label" for="acc_def">Default for this type</label></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="acc_act" name="is_active" <?= $f['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="acc_act">Active</label></div>
                    <div class="d-flex gap-2"><button class="btn btn-primary flex-fill"><?= $editing ? 'Save' : 'Add' ?> account</button><?php if ($editing): ?><a href="cash-bank.php" class="btn btn-light-brand">Cancel</a><?php endif; ?></div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$inlineJs = "document.getElementById('adjType').addEventListener('change', function () { var t = this.value === 'transfer'; document.getElementById('toWrap').style.display = t ? '' : 'none'; document.getElementById('accLabel').textContent = t ? 'From account' : 'Account'; });";
require __DIR__ . '/partials/footer.php';
