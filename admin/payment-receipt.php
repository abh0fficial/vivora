<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$pay = q_row("SELECT p.*, i.invoice_no, i.grand_total inv_total, i.amount_paid inv_paid FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id WHERE p.id = ? AND p.direction = 'in'", [(int) get('id')]);
if (!$pay) {
    flash('error', 'Receipt not found.');
    redirect('admin/payments.php');
}
$company = setting('company_name', 'Vivora Healthcare');
$pageTitle = 'Receipt ' . $pay['receipt_no'];
$activeNav = 'payments-in';
$breadcrumbs = ['Payments' => 'payments.php', $pay['receipt_no'] => null];
$pageActions = '<button onclick="window.print()" class="btn btn-primary"><i class="feather-printer me-2"></i>Print / Save PDF</button>';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4 pb-4 border-bottom">
                    <div>
                        <img src="<?= asset('images/logo-full.png') ?>" alt="<?= e($company) ?>" style="max-width:200px" class="mb-2">
                        <div class="fs-12 text-muted"><?= setting('address') ? nl2br(e(setting('address'))) . '<br>' : '' ?><?= setting('phone') ? e(setting('phone')) . '<br>' : '' ?><?= setting('gstin') ? 'GSTIN: ' . e(setting('gstin')) : '' ?></div>
                    </div>
                    <div class="text-end">
                        <h3 class="fw-bolder text-primary mb-2">PAYMENT RECEIPT</h3>
                        <div class="fs-13">Receipt no. <strong><?= e($pay['receipt_no']) ?></strong></div>
                        <div class="fs-13">Date: <?= e(fmt_date($pay['payment_date'])) ?></div>
                    </div>
                </div>
                <p class="fs-15">Received with thanks from <strong><?= e($pay['party_name']) ?></strong> the sum of
                    <strong><?= money($pay['amount']) ?></strong> (<?= e(amount_in_words((float) $pay['amount'])) ?>)
                    by <strong><?= e(payment_modes()[$pay['mode']] ?? $pay['mode']) ?></strong><?= $pay['reference'] ? ' (ref. ' . e($pay['reference']) . ')' : '' ?>
                    <?= $pay['invoice_no'] ? ' against invoice <strong>' . e($pay['invoice_no']) . '</strong>' : ' on account' ?>.</p>
                <?php if ($pay['invoice_no']): ?>
                    <table class="table table-sm fs-13 mt-4" style="max-width:360px">
                        <tr><td class="text-muted">Invoice total</td><td class="text-end"><?= money($pay['inv_total']) ?></td></tr>
                        <tr><td class="text-muted">Total received so far</td><td class="text-end"><?= money($pay['inv_paid']) ?></td></tr>
                        <tr><td class="fw-bold">Balance due</td><td class="text-end fw-bold"><?= money(max(0, $pay['inv_total'] - $pay['inv_paid'])) ?></td></tr>
                    </table>
                <?php endif; ?>
                <?php if ($pay['notes']): ?><p class="fs-12 text-muted">Note: <?= e($pay['notes']) ?></p><?php endif; ?>
                <div class="d-flex justify-content-between align-items-end mt-5">
                    <div class="fs-11 text-muted">This is a computer-generated receipt.</div>
                    <div class="text-center"><div style="height:50px"></div><div class="border-top pt-2 fs-12">For <?= e($company) ?><br>Authorised Signatory</div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
