<?php
require __DIR__ . '/includes/public.php';

$types = service_types();
$errors = [];
$old = ['name' => '', 'organization' => '', 'email' => '', 'phone' => '', 'city' => '', 'equipment' => '', 'brand_model' => '', 'serial_no' => '',
    'request_type' => array_key_exists(get('type'), $types) ? get('type') : 'repair', 'description' => '', 'preferred_date' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($old as $k => $_) $old[$k] = post($k);
    if ($err = spam_check('service_requests')) {
        $errors[] = $err;
    } else {
        if ($old['name'] === '') $errors[] = 'Please enter your name.';
        if (!valid_phone($old['phone'])) $errors[] = 'Please enter a valid phone number.';
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (!array_key_exists($old['request_type'], $types)) $old['request_type'] = 'other';
        if ($old['equipment'] === '') $errors[] = 'Please tell us which equipment needs service.';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['preferred_date']) && $old['preferred_date'] >= date('Y-m-d') ? $old['preferred_date'] : null;
    }
    if (!$errors) {
        q('INSERT INTO service_requests (name, organization, email, phone, city, equipment, brand_model, serial_no, request_type, description, preferred_date, ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            mb_substr($old['name'], 0, 160), mb_substr($old['organization'], 0, 200), mb_substr($old['email'], 0, 160), mb_substr($old['phone'], 0, 30),
            mb_substr($old['city'], 0, 80), mb_substr($old['equipment'], 0, 200), mb_substr($old['brand_model'], 0, 200), mb_substr($old['serial_no'], 0, 100),
            $old['request_type'], mb_substr($old['description'], 0, 5000), $date, client_ip(),
        ]);
        $id = (int) db()->lastInsertId();
        $ticket = 'SR-' . date('y') . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        q('UPDATE service_requests SET ticket_no = ? WHERE id = ?', [$ticket, $id]);
        notify_admin("New service request $ticket – " . $types[$old['request_type']], [
            'Ticket' => $ticket, 'Type' => $types[$old['request_type']], 'Name' => $old['name'], 'Organisation' => $old['organization'],
            'Phone' => $old['phone'], 'Email' => $old['email'], 'City' => $old['city'], 'Equipment' => $old['equipment'] . ' ' . $old['brand_model'],
            'Serial' => $old['serial_no'], 'Preferred date' => $date ?? '', 'Details' => $old['description'],
        ]);
        flash('success', "Your service request has been registered. Ticket number: $ticket. Our service team will contact you shortly.");
        redirect('service.php');
    }
}

$meta = page_meta('Service & Support', 'Installation, repair, AMC and calibration services for medical equipment by ' . setting('company_name', 'Vivora Healthcare') . '.');
$activePage = 'service';
require __DIR__ . '/partials/site-header.php';
?>
<section class="vh-page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= base_url('index.php') ?>">Home</a></li><li class="breadcrumb-item active">Service &amp; Support</li></ol></nav>
        <h1>Service &amp; Support</h1>
        <p>Installation, repairs, preventive maintenance, calibration and training — keep your equipment running reliably.</p>
    </div>
</section>

<section class="vh-section pt-5">
    <div class="container">
        <div class="row g-4 mb-5">
            <?php foreach ([
                ['package', 'Installation & Demo', 'Professional installation and hands-on demonstration at your facility.', 'installation'],
                ['tool', 'Repair / Breakdown', 'Diagnosis and repair to get critical equipment back in service.', 'repair'],
                ['calendar', 'AMC / CMC', 'Annual maintenance contracts with scheduled preventive visits.', 'amc'],
                ['sliders', 'Calibration', 'Periodic calibration checks to keep readings accurate.', 'calibration'],
            ] as [$icon, $title, $text, $key]): ?>
                <div class="col-sm-6 col-lg-3">
                    <a href="?type=<?= $key ?>#request" class="vh-feature vh-feature-link h-100">
                        <span class="vh-feature-icon"><i class="feather-<?= $icon ?>"></i></span>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($text) ?></p>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-5">
            <div class="col-lg-8">
                <div class="vh-form-card" id="request">
                    <h2 class="vh-h3">Raise a service request</h2>
                    <p class="text-muted small mb-4">You'll get a ticket number instantly. Our team will call you to schedule the visit.</p>
                    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
                    <form method="post" action="#request" novalidate>
                        <?= spam_fields() ?>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="s-type">Service needed *</label>
                                <select id="s-type" name="request_type" class="form-select"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $old['request_type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                            </div>
                            <div class="col-md-6"><label class="form-label" for="s-date">Preferred visit date</label><input id="s-date" type="date" name="preferred_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($old['preferred_date']) ?>"></div>
                            <div class="col-md-4"><label class="form-label" for="s-eq">Equipment *</label><input id="s-eq" name="equipment" class="form-control" value="<?= e($old['equipment']) ?>" placeholder="e.g. Patient monitor" required maxlength="200"></div>
                            <div class="col-md-4"><label class="form-label" for="s-bm">Brand / Model</label><input id="s-bm" name="brand_model" class="form-control" value="<?= e($old['brand_model']) ?>" maxlength="200"></div>
                            <div class="col-md-4"><label class="form-label" for="s-sn">Serial no.</label><input id="s-sn" name="serial_no" class="form-control" value="<?= e($old['serial_no']) ?>" maxlength="100"></div>
                            <div class="col-12"><label class="form-label" for="s-desc">Describe the issue / requirement</label><textarea id="s-desc" name="description" class="form-control" rows="4" maxlength="5000"><?= e($old['description']) ?></textarea></div>
                            <div class="col-md-6"><label class="form-label" for="s-name">Your name *</label><input id="s-name" name="name" class="form-control" value="<?= e($old['name']) ?>" required maxlength="160" autocomplete="name"></div>
                            <div class="col-md-6"><label class="form-label" for="s-phone">Phone *</label><input id="s-phone" name="phone" type="tel" class="form-control" value="<?= e($old['phone']) ?>" required maxlength="20" autocomplete="tel"></div>
                            <div class="col-md-6"><label class="form-label" for="s-org">Hospital / Clinic</label><input id="s-org" name="organization" class="form-control" value="<?= e($old['organization']) ?>" maxlength="200"></div>
                            <div class="col-md-3"><label class="form-label" for="s-city">City</label><input id="s-city" name="city" class="form-control" value="<?= e($old['city']) ?>" maxlength="80"></div>
                            <div class="col-md-3"><label class="form-label" for="s-email">Email</label><input id="s-email" name="email" type="email" class="form-control" value="<?= e($old['email']) ?>" maxlength="160"></div>
                            <div class="col-12"><button class="btn btn-primary btn-lg vh-btn px-5">Submit request</button></div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="vh-contact-card">
                    <h2 class="vh-h3 text-white">Urgent breakdown?</h2>
                    <p class="text-white-50">For critical equipment failures, call or WhatsApp our service desk directly.</p>
                    <div class="d-grid gap-2 mt-4">
                        <?php if (setting('phone')): ?><a href="<?= e(tel_link(setting('phone'))) ?>" class="btn btn-light vh-btn"><i class="feather-phone me-2"></i><?= e(setting('phone')) ?></a><?php endif; ?>
                        <?php if (whatsapp_link()): ?><a href="<?= e(whatsapp_link('Urgent service required for my equipment.')) ?>" target="_blank" rel="noopener" class="btn btn-outline-light vh-btn"><i class="feather-message-circle me-2"></i>WhatsApp service desk</a><?php endif; ?>
                    </div>
                    <hr class="border-light opacity-25 my-4">
                    <h3 class="h6 text-white">What to keep ready</h3>
                    <ul class="text-white-50 small ps-3 mb-0">
                        <li>Equipment brand, model &amp; serial number</li>
                        <li>Photos or error codes shown on screen</li>
                        <li>Invoice / warranty details, if available</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/site-footer.php';
