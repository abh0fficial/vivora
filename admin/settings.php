<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$groups = [
    'Company' => [
        'company_name' => ['Company name', 'text'],
        'tagline' => ['Tagline', 'text'],
        'gstin' => ['GSTIN', 'text'],
        'business_hours' => ['Business hours', 'text'],
    ],
    'Contact details (shown on website & quotations)' => [
        'phone' => ['Primary phone', 'text'],
        'phone2' => ['Alternate phone', 'text'],
        'whatsapp' => ['WhatsApp number (with country code, e.g. 919876543210)', 'text'],
        'email' => ['Email', 'email'],
        'address' => ['Address', 'textarea'],
        'city' => ['City / State / PIN', 'text'],
        'map_embed_url' => ['Google Maps embed URL (optional)', 'url'],
    ],
    'Website content' => [
        'hero_title' => ['Home page headline', 'text'],
        'hero_subtitle' => ['Home page sub-headline', 'textarea'],
        'about_text' => ['About us', 'textarea'],
        'meta_description' => ['SEO description', 'textarea'],
    ],
    'Social links' => [
        'facebook' => ['Facebook URL', 'url'],
        'instagram' => ['Instagram URL', 'url'],
        'linkedin' => ['LinkedIn URL', 'url'],
        'youtube' => ['YouTube URL', 'url'],
    ],
    'Quotations' => [
        'quote_prefix' => ['Quotation number prefix', 'text'],
        'quote_validity_days' => ['Default validity (days)', 'number'],
        'quote_terms' => ['Default terms & conditions', 'textarea'],
        'bank_details' => ['Bank details (printed on quotations)', 'textarea'],
    ],
    'Notifications' => [
        'notify_email' => ['Email me new enquiries & service requests at (uses PHP mail on Hostinger)', 'email'],
    ],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $errors = [];
    foreach ($groups as $fields) {
        foreach ($fields as $key => [$label, $type]) {
            $val = post($key);
            if ($type === 'url' && $val !== '' && !preg_match('~^https://~i', $val)) {
                $errors[] = "$label must start with https://";
                continue;
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
                                <?php if ($type === 'textarea'): ?>
                                    <textarea id="s_<?= $key ?>" name="<?= $key ?>" class="form-control" rows="<?= in_array($key, ['about_text', 'quote_terms'], true) ? 6 : 3 ?>"><?= e(setting($key)) ?></textarea>
                                <?php else: ?>
                                    <input id="s_<?= $key ?>" type="<?= $type === 'url' ? 'url' : $type ?>" name="<?= $key ?>" class="form-control" value="<?= e(setting($key)) ?>">
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
