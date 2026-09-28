<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$cats = expense_categories();
$modes = payment_modes();
$editId = (int) get('edit');
$editing = $editId ? q_row('SELECT * FROM expenses WHERE id = ?', [$editId]) : null;
$form = $editing ?: ['expense_date' => date('Y-m-d'), 'category' => $cats[0], 'description' => '', 'paid_to' => '', 'amount' => '', 'mode' => 'cash', 'reference' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    if (post('action') === 'delete') {
        $x = q_row('SELECT * FROM expenses WHERE id = ?', [(int) post('id')]);
        if ($x) {
            q('DELETE FROM expenses WHERE id = ?', [$x['id']]);
            log_activity('expense.delete', 'Deleted expense ' . money($x['amount']) . ' (' . $x['category'] . ')');
            flash('success', 'Expense deleted.');
        }
        redirect('admin/expenses.php');
    }
    $form = [
        'expense_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', post('expense_date')) ? post('expense_date') : date('Y-m-d'),
        'category' => mb_substr(post('category') ?: 'Other', 0, 80), 'description' => mb_substr(post('description'), 0, 300),
        'paid_to' => mb_substr(post('paid_to'), 0, 160), 'amount' => round((float) post('amount'), 2),
        'mode' => array_key_exists(post('mode'), $modes) ? post('mode') : 'cash', 'reference' => mb_substr(post('reference'), 0, 120),
    ];
    if ($form['amount'] <= 0) {
        flash('error', 'Enter the expense amount.');
    } else {
        $cols = array_keys($form);
        if ($id = (int) post('id')) {
            q('UPDATE expenses SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', array_merge(array_values($form), [$id]));
            flash('success', 'Expense updated.');
        } else {
            q('INSERT INTO expenses (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($form));
            flash('success', 'Expense of ' . money($form['amount']) . ' recorded.');
        }
        log_activity('expense.save', 'Expense ' . money($form['amount']) . ' – ' . $form['category']);
        redirect('admin/expenses.php');
    }
}

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : date('Y-m-d');
$cat = get('category');
$params = [$from, $to];
$sql = 'SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ?';
if ($cat !== '') { $sql .= ' AND category = ?'; $params[] = $cat; }
$rows = q_all($sql . ' ORDER BY expense_date DESC, id DESC', $params);
$byCat = [];
foreach ($rows as $r) $byCat[$r['category']] = ($byCat[$r['category']] ?? 0) + (float) $r['amount'];
arsort($byCat);
$allCats = array_values(array_unique(array_merge($cats, array_column(q_all('SELECT DISTINCT category FROM expenses'), 'category'))));

$pageTitle = 'Expenses';
$activeNav = 'expenses';
$breadcrumbs = ['Expenses' => null];
$pageActions = '<a href="export.php?type=expenses" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a>';
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header flex-wrap gap-2">
                <h5 class="card-title">Expenses · <?= money(array_sum($byCat)) ?></h5>
                <form method="get" class="ms-auto d-flex gap-2 flex-wrap">
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
                    <select name="category" class="form-select form-select-sm"><option value="">All categories</option><?php foreach ($allCats as $c): ?><option <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-sm btn-primary">Go</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Paid to</th><th>Mode</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="text-nowrap"><?= e(fmt_date($r['expense_date'])) ?></td>
                                <td><span class="badge bg-gray-200 text-dark"><?= e($r['category']) ?></span></td>
                                <td><?= e($r['description']) ?></td>
                                <td><?= e($r['paid_to']) ?></td>
                                <td class="fs-12"><?= e($modes[$r['mode']] ?? $r['mode']) ?><?= $r['reference'] ? '<br><span class="text-muted">' . e($r['reference']) . '</span>' : '' ?></td>
                                <td class="text-end fw-semibold"><?= money($r['amount']) ?></td>
                                <td class="text-end"><div class="hstack gap-2 justify-content-end">
                                    <a href="expenses.php?edit=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a>
                                    <form method="post" class="m-0" data-confirm="Delete this expense?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button></form>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">No expenses in this period.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title"><?= $editing ? 'Edit expense' : 'Add expense' ?></h5></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?= e($form['expense_date']) ?>"></div>
                        <div class="col-6 mb-3"><label class="form-label">Amount (₹)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e($form['amount']) ?>" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Category</label><input name="category" list="expCats" class="form-control" value="<?= e($form['category']) ?>" maxlength="80">
                        <datalist id="expCats"><?php foreach ($allCats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
                    <div class="mb-3"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= e($form['description']) ?>" maxlength="300"></div>
                    <div class="mb-3"><label class="form-label">Paid to</label><input name="paid_to" class="form-control" value="<?= e($form['paid_to']) ?>" maxlength="160"></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Mode</label><select name="mode" class="form-select"><?php foreach ($modes as $k => $v): ?><option value="<?= $k ?>" <?= $form['mode'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
                        <div class="col-6 mb-3"><label class="form-label">Reference</label><input name="reference" class="form-control" value="<?= e($form['reference']) ?>" maxlength="120"></div>
                    </div>
                    <div class="d-flex gap-2"><button class="btn btn-primary flex-fill"><?= $editing ? 'Update' : 'Add' ?> expense</button><?php if ($editing): ?><a href="expenses.php" class="btn btn-light-brand">Cancel</a><?php endif; ?></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title">By category</h5></div>
            <div class="card-body">
                <?php foreach ($byCat as $k => $v): ?><div class="d-flex justify-content-between mb-2"><span><?= e($k) ?></span><strong><?= money($v) ?></strong></div><?php endforeach; ?>
                <?php if (!$byCat): ?><p class="text-muted fs-12 mb-0">Nothing yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
