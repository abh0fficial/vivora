<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$qt = q_row('SELECT * FROM quotations WHERE id = ?', [$id]);
if (!$qt) {
    flash('error', 'Quotation not found.');
    redirect('admin/quotations.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $action = post('action');
    if ($action === 'status' && in_array(post('status'), ['draft', 'sent', 'accepted', 'rejected'], true)) {
        q('UPDATE quotations SET status = ? WHERE id = ?', [post('status'), $id]);
        if ($qt['enquiry_id'] && post('status') !== 'draft') {
            q("UPDATE enquiries SET status = ? WHERE id = ? AND status <> 'won'", [['sent' => 'quoted', 'accepted' => 'won', 'rejected' => 'lost'][post('status')], $qt['enquiry_id']]);
        }
        log_activity('quote.status', "Quotation {$qt['quote_no']} marked " . post('status'));
        flash('success', 'Quotation marked as ' . post('status') . '.');
    } elseif ($action === 'duplicate') {
        $no = next_quote_no();
        q('INSERT INTO quotations (quote_no, customer_id, customer_name, customer_org, customer_email, customer_phone, customer_address, customer_gstin,
            quote_date, valid_until, status, subtotal, discount, tax_total, grand_total, notes, terms)
            SELECT ?, customer_id, customer_name, customer_org, customer_email, customer_phone, customer_address, customer_gstin,
            CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), \'draft\', subtotal, discount, tax_total, grand_total, notes, terms FROM quotations WHERE id = ?',
            [$no, (int) setting('quote_validity_days', '15'), $id]);
        $newId = (int) db()->lastInsertId();
        q('INSERT INTO quotation_items (quotation_id, product_id, description, qty, unit_price, gst_rate, line_total, sort_order)
            SELECT ?, product_id, description, qty, unit_price, gst_rate, line_total, sort_order FROM quotation_items WHERE quotation_id = ?', [$newId, $id]);
        log_activity('quote.create', "Duplicated {$qt['quote_no']} as $no");
        flash('success', "Created $no as a copy.");
        redirect('admin/quotation-form.php?id=' . $newId);
    } elseif ($action === 'delete') {
        q('DELETE FROM quotations WHERE id = ?', [$id]);
        log_activity('quote.delete', "Deleted quotation {$qt['quote_no']}");
        flash('success', 'Quotation deleted.');
        redirect('admin/quotations.php');
    }
    redirect('admin/quotation-view.php?id=' . $id);
}

$items = q_all('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id', [$id]);
$company = setting('company_name', 'Vivora Healthcare');
$companyAddress = trim(setting('address') . (setting('city') ? ', ' . setting('city') : ''), ', ');
$wa = preg_replace('/\D+/', '', $qt['customer_phone']);
if (strlen($wa) === 10) $wa = '91' . $wa;
$waText = "Dear {$qt['customer_name']},\nPlease find our quotation {$qt['quote_no']} dated " . fmt_date($qt['quote_date']) . ' for ' . money($qt['grand_total']) . " (incl. GST).\n— $company";

$pageTitle = 'Quotation ' . $qt['quote_no'];
$activeNav = 'quotations';
$breadcrumbs = ['Quotations' => 'quotations.php', $qt['quote_no'] => null];
$invoiced = q_row('SELECT id, invoice_no FROM invoices WHERE quotation_id = ? ORDER BY id DESC LIMIT 1', [$id]);
$pageActions = ($invoiced
        ? '<a href="invoice-view.php?id=' . (int) $invoiced['id'] . '" class="btn btn-success"><i class="feather-check me-2"></i>Invoiced: ' . e($invoiced['invoice_no']) . '</a>'
        : '<a href="invoice-form.php?quotation_id=' . $id . '" class="btn btn-success"><i class="feather-file-plus me-2"></i>Convert to invoice</a>')
    . '<button onclick="window.print()" class="btn btn-primary"><i class="feather-printer me-2"></i>Print / Save PDF</button>'
    . '<a href="quotation-form.php?id=' . $id . '" class="btn btn-light-brand"><i class="feather-edit-3 me-2"></i>Edit</a>';
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-12 vh-doc-main">
        <div class="card" id="printArea">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-4 mb-5">
                    <div>
                        <img src="<?= asset('images/logo-full.png') ?>" alt="<?= e($company) ?>" style="max-width:230px" class="mb-3">
                        <div class="fs-12 text-muted" style="max-width:320px">
                            <?= $companyAddress ? e($companyAddress) . '<br>' : '' ?>
                            <?= setting('phone') ? 'Phone: ' . e(setting('phone')) . '<br>' : '' ?>
                            <?= setting('email') ? 'Email: ' . e(setting('email')) . '<br>' : '' ?>
                            <?= setting('gstin') ? 'GSTIN: ' . e(setting('gstin')) : '' ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <h2 class="fw-bolder text-primary mb-2">QUOTATION</h2>
                        <div class="fs-13"><span class="text-muted">No:</span> <strong><?= e($qt['quote_no']) ?></strong></div>
                        <div class="fs-13"><span class="text-muted">Date:</span> <?= e(fmt_date($qt['quote_date'])) ?></div>
                        <?php if ($qt['valid_until']): ?><div class="fs-13"><span class="text-muted">Valid until:</span> <?= e(fmt_date($qt['valid_until'])) ?></div><?php endif; ?>
                        <div class="mt-2 no-print"><?= status_badge($qt['status']) ?></div>
                    </div>
                </div>

                <div class="mb-5 p-3 rounded bg-gray-100">
                    <div class="fs-11 text-uppercase text-muted fw-bold mb-1">Quotation for</div>
                    <div class="fw-bold text-dark"><?= e($qt['customer_org'] ?: $qt['customer_name']) ?></div>
                    <?php if ($qt['customer_org'] && $qt['customer_name']): ?><div>Kind attn: <?= e($qt['customer_name']) ?></div><?php endif; ?>
                    <div class="fs-12 text-muted">
                        <?= $qt['customer_address'] ? e($qt['customer_address']) . '<br>' : '' ?>
                        <?= e(implode(' · ', array_filter([$qt['customer_phone'], $qt['customer_email']]))) ?>
                        <?= $qt['customer_gstin'] ? '<br>GSTIN: ' . e($qt['customer_gstin']) : '' ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg-gray-100"><tr><th style="width:40px">#</th><th>Item / Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">GST</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $i => $it): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($it['description']) ?></td>
                                <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2), '0'), '.')) ?></td>
                                <td class="text-end"><?= money($it['unit_price']) ?></td>
                                <td class="text-end"><?= e(rtrim(rtrim((string) $it['gst_rate'], '0'), '.')) ?>%</td>
                                <td class="text-end"><?= money($it['line_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4">
                    <div class="col-md-7">
                        <div class="fs-12 text-muted mb-1">Amount in words</div>
                        <div class="fw-semibold mb-4"><?= e(amount_in_words((float) $qt['grand_total'])) ?></div>
                        <?php if ($qt['notes']): ?><div class="fs-12 text-muted mb-1">Notes</div><div class="mb-4" style="white-space:pre-line"><?= e($qt['notes']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <table class="table table-sm">
                            <tr><td class="text-muted">Subtotal</td><td class="text-end"><?= money($qt['subtotal']) ?></td></tr>
                            <?php if ((float) $qt['discount'] > 0): ?><tr><td class="text-muted">Discount</td><td class="text-end">− <?= money($qt['discount']) ?></td></tr><?php endif; ?>
                            <tr><td class="text-muted">GST</td><td class="text-end"><?= money($qt['tax_total']) ?></td></tr>
                            <tr class="fs-5"><td class="fw-bold">Grand Total</td><td class="text-end fw-bold text-primary"><?= money($qt['grand_total']) ?></td></tr>
                        </table>
                    </div>
                </div>

                <?php if ($qt['terms']): ?>
                    <div class="mt-4"><div class="fs-12 text-uppercase text-muted fw-bold mb-2">Terms & Conditions</div><div class="fs-12" style="white-space:pre-line"><?= e($qt['terms']) ?></div></div>
                <?php endif; ?>
                <?php if (setting('bank_details')): ?>
                    <div class="mt-4"><div class="fs-12 text-uppercase text-muted fw-bold mb-2">Bank Details</div><div class="fs-12" style="white-space:pre-line"><?= e(setting('bank_details')) ?></div></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-end mt-5 pt-4">
                    <div class="fs-12 text-muted"><?= e(setting('tagline', 'Better Equipment. Healthier Tomorrows.')) ?></div>
                    <div class="text-center"><div style="height:50px"></div><div class="border-top pt-2 fs-12">For <?= e($company) ?><br>Authorised Signatory</div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 vh-doc-side no-print">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Actions</h5></div>
            <div class="card-body d-grid gap-2">
                <form method="post" class="d-flex gap-2">
                    <?= csrf_field() ?><input type="hidden" name="action" value="status">
                    <select name="status" class="form-select">
                        <?php foreach (['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $qt['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary">Update</button>
                </form>
                <?php if (strlen($wa) >= 11): ?>
                    <a href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" class="btn btn-success"><i class="feather-message-circle me-2"></i>Share on WhatsApp</a>
                <?php endif; ?>
                <?php if ($qt['customer_email']): ?>
                    <a href="mailto:<?= e($qt['customer_email']) ?>?subject=<?= rawurlencode("Quotation {$qt['quote_no']} – $company") ?>&body=<?= rawurlencode($waText) ?>" class="btn btn-light-brand"><i class="feather-mail me-2"></i>Email customer</a>
                <?php endif; ?>
                <form method="post"><?= csrf_field() ?><button name="action" value="duplicate" class="btn btn-light-brand w-100"><i class="feather-copy me-2"></i>Duplicate</button></form>
                <form method="post" data-confirm="Delete this quotation permanently?"><?= csrf_field() ?><button name="action" value="delete" class="btn btn-outline-danger w-100"><i class="feather-trash-2 me-2"></i>Delete</button></form>
                <p class="fs-11 text-muted mb-0 mt-2">Tip: use <strong>Print / Save PDF</strong> and choose “Save as PDF” to email a PDF copy.</p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
