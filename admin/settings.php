<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_owner();

$groups = [
    'Company' => [
        'company_name' => ['Company name', 'text'],
        'tagline' => ['Tagline', 'text'],
        'gstin' => ['GSTIN', 'text'],
    ],
    'Contact details (printed on quotations)' => [
        'phone' => ['Primary phone', 'text'],
        'phone2' => ['Alternate phone', 'text'],
        'whatsapp' => ['WhatsApp number (with country code, e.g. 919876543210)', 'text'],
        'email' => ['Email', 'email'],
        'address' => ['Address', 'textarea'],
        'city' => ['City / State / PIN', 'text'],
    ],
    'Billing & invoices' => [
        'company_state' => ['Company state (decides CGST + SGST or IGST)', 'state'],
        'invoice_prefix' => ['Invoice number prefix (financial year is added, e.g. VH-INV-2026-27/0001)', 'text'],
        'receipt_prefix' => ['Payment receipt prefix', 'text'],
        'invoice_due_days' => ['Default payment terms (days)', 'number'],
        'upi_id' => ['UPI ID (printed on invoices)', 'text'],
        'invoice_terms' => ['Default invoice terms & conditions', 'textarea'],
        'expense_categories' => ['Expense categories (one per line)', 'textarea'],
    ],
    'Quotations' => [
        'quote_prefix' => ['Quotation number prefix', 'text'],
        'quote_validity_days' => ['Default validity (days)', 'number'],
        'quote_terms' => ['Default terms & conditions', 'textarea'],
        'bank_details' => ['Bank details (printed on quotations)', 'textarea'],
    ],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $errors = [];
    foreach ($groups as $fields) {
        foreach ($fields as $key => [$label, $type]) {
            $val = post($key);
            if ($type === 'url' && $val !== '') {
                $val = preg_replace('~^http://~i', 'https://', $val);
                if (!preg_match('~^https://~i', $val)) {
                    $val = 'https://' . ltrim($val, '/');
                }
            }
            if ($type === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "$label is not a valid email.";
                continue;
            }
            save_setting($key, $val);
        }
    }
    log_activity('settings.update', 'Updated company settings');
    foreach ($errors as $err) flash('error', $err);
    if (!$errors) flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}

$pageTitle = 'Company Settings';
$activeNav = 'settings';
$breadcrumbs = ['Settings' => null];
require __DIR__ . '/partials/header.php';
?>
<form method="post">
    <?= csrf_field() ?>
    <div class="row">
        <?php foreach ($groups as $title => $fields): ?>
            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h5 class="card-title"><?= e($title) ?></h5></div>
                    <div class="card-body">
                        <?php foreach ($fields as $key => [$label, $type]): ?>
                            <div class="mb-3">
                                <label class="form-label" for="s_<?= $key ?>"><?= e($label) ?></label>
                                <?php if ($type === 'state'): ?>
                                    <select id="s_<?= $key ?>" name="<?= $key ?>" class="form-select"><option value="">— Select state —</option>
                                        <?php foreach (indian_states() as $code => $st): ?><option value="<?= e($st) ?>" <?= setting($key) === $st ? 'selected' : '' ?>><?= $code ?> – <?= e($st) ?></option><?php endforeach; ?>
                                    </select>
                                <?php elseif ($type === 'textarea'): ?>
                                    <textarea id="s_<?= $key ?>" name="<?= $key ?>" class="form-control" rows="<?= in_array($key, ['quote_terms', 'invoice_terms', 'expense_categories'], true) ? 6 : 3 ?>"><?= e(setting($key)) ?></textarea>
                                <?php else: ?>
                                    <input id="s_<?= $key ?>" type="<?= in_array($type, ['url', 'email'], true) ? 'text' : $type ?>" name="<?= $key ?>" class="form-control" value="<?= e(setting($key)) ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card"><div class="card-body d-flex justify-content-end"><button class="btn btn-primary btn-lg"><i class="feather-save me-2"></i>Save settings</button></div></div>
</form>
<?php require __DIR__ . '/partials/footer.php';
