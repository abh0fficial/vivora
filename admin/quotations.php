<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

// Delete straight from the list (with confirmation in the browser).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'delete') {
    require_post();
    $row = q_row('SELECT * FROM quotations WHERE id = ?', [(int) post('id')]);
    if ($row) {
        q('DELETE FROM quotations WHERE id = ?', [$row['id']]);
        log_activity('quotation.delete', 'Deleted quotation ' . $row['quote_no']);
        flash('success', 'Quotation ' . $row['quote_no'] . ' deleted.');
    }
    redirect('admin/quotations.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$statuses = ['draft', 'sent', 'accepted', 'rejected'];
$status = in_array(get('status'), $statuses, true) ? get('status') : '';
$rows = q_all('SELECT qt.*, (SELECT COUNT(*) FROM quotation_items i WHERE i.quotation_id = qt.id) item_count FROM quotations qt'
    . ($status ? ' WHERE qt.status = ?' : '') . ' ORDER BY qt.quote_date DESC, qt.id DESC', $status ? [$status] : []);
$totals = [];
foreach (q_all('SELECT status, COUNT(*) c, COALESCE(SUM(grand_total),0) s FROM quotations GROUP BY status') as $r) $totals[$r['status']] = $r;

$pageTitle = 'Quotations';
$activeNav = 'quotations';
$breadcrumbs = ['Quotations' => null];
$pageActions = '<a href="export.php?type=quotations" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a>'
    . '<a href="quotation-form.php" class="btn btn-primary"><i class="feather-plus me-2"></i>Create Quotation</a>';
$extraCss = ['vendors/css/dataTables.bs5.min.css'];
$extraJs = ['vendors/js/dataTables.min.js', 'vendors/js/dataTables.bs5.min.js'];
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <?php foreach (['draft' => ['Drafts', 'edit', 'secondary'], 'sent' => ['Sent / pending', 'send', 'info'], 'accepted' => ['Accepted', 'check-circle', 'success'], 'rejected' => ['Rejected', 'x-circle', 'danger']] as $k => [$label, $icon, $color]): ?>
        <div class="col-xxl-3 col-md-6">
            <a href="quotations.php?status=<?= $k ?>" class="card stretch stretch-full text-reset">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-text avatar-lg bg-soft-<?= $color ?> text-<?= $color ?>"><i class="feather-<?= $icon ?>"></i></span>
                    <div>
                        <div class="fs-5 fw-bold text-dark"><?= money($totals[$k]['s'] ?? 0) ?></div>
                        <div class="fs-12 text-muted"><?= (int) ($totals[$k]['c'] ?? 0) ?> <?= $label ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="card-header">
        <ul class="nav nav-pills gap-1">
            <?php foreach (array_merge([''], $statuses) as $s): ?>
                <li class="nav-item"><a class="nav-link py-1 px-3<?= $status === $s ? ' active' : '' ?>" href="quotations.php<?= $s ? '?status=' . $s : '' ?>"><?= $s ? ucfirst($s) : 'All' ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card-body">
        <?php if (!$rows): ?>
            <div class="vh-empty"><i class="feather-file-text"></i>No quotations yet. <a href="quotation-form.php">Create your first quotation</a>.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="qTable">
                <thead><tr><th>Quote #</th><th>Customer</th><th>Date</th><th>Valid until</th><th>Items</th><th>Status</th><th class="text-end">Total</th><th data-orderable="false"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): $expired = $r['status'] === 'sent' && $r['valid_until'] && $r['valid_until'] < date('Y-m-d'); ?>
                    <tr>
                        <td><a href="quotation-view.php?id=<?= (int) $r['id'] ?>" class="fw-semibold"><?= e($r['quote_no']) ?></a></td>
                        <td><span class="text-dark fw-semibold d-block"><?= e($r['customer_org'] ?: $r['customer_name']) ?></span><?php if ($r['customer_org']): ?><span class="fs-12 text-muted"><?= e($r['customer_name']) ?></span><?php endif; ?></td>
                        <td data-order="<?= e($r['quote_date']) ?>"><?= e(fmt_date($r['quote_date'])) ?></td>
                        <td class="<?= $expired ? 'text-danger' : '' ?>"><?= e(fmt_date($r['valid_until'])) ?><?= $expired ? ' <span class="fs-11">(expired)</span>' : '' ?></td>
                        <td><?= (int) $r['item_count'] ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="text-end fw-semibold" data-order="<?= (float) $r['grand_total'] ?>"><?= money($r['grand_total']) ?></td>
                        <td class="text-end">
                            <div class="hstack gap-2 justify-content-end">
                                <a href="quotation-view.php?id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="View / print"><i class="feather-printer"></i></a>
                                <a href="quotation-form.php?id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a>
                                <form method="post" class="m-0" data-confirm="Delete quotation <?= e($r['quote_no']) ?> permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$inlineJs = "if ($.fn.DataTable && $('#qTable').length) { $('#qTable').DataTable({ pageLength: 25, order: [] }); }";
require __DIR__ . '/partials/footer.php';
