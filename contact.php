<?php
require __DIR__ . '/includes/public.php';

$isQuote = get('type') === 'quote' || post('source') === 'quote';
$products = q_all('SELECT p.id, p.name, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY c.sort_order, p.name');
$errors = [];
$old = ['name' => '', 'organization' => '', 'email' => '', 'phone' => '', 'city' => '', 'quantity' => '', 'subject' => get('subject'), 'message' => '', 'product_id' => get('product')];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    [$errors, $old] = handle_enquiry_post($isQuote ? 'quote' : 'contact', null, 'contact.php' . ($isQuote ? '?type=quote' : ''));
}
$mapUrl = setting('map_embed_url');
$mapOk = (bool) preg_match('~^https://(www\.)?google\.[a-z.]+/maps/embed~i', $mapUrl);

$meta = page_meta($isQuote ? 'Request a Quote' : 'Contact Us', 'Contact ' . setting('company_name', 'Vivora Healthcare') . ' for prices, quotations and product information.');
$activePage = 'contact';
require __DIR__ . '/partials/site-header.php';
?>
<section class="vh-page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= base_url('index.php') ?>">Home</a></li><li class="breadcrumb-item active"><?= $isQuote ? 'Request a Quote' : 'Contact' ?></li></ol></nav>
        <h1><?= $isQuote ? 'Request a Quote' : 'Contact Us' ?></h1>
        <p><?= $isQuote ? 'Tell us what equipment you need and we\'ll send you a detailed quotation.' : 'Questions about a product, pricing or delivery? We\'re here to help.' ?></p>
    </div>
</section>

<section class="vh-section pt-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                <div class="vh-form-card">
                    <ul class="nav nav-pills vh-tabs mb-4">
                        <li class="nav-item"><a class="nav-link<?= $isQuote ? ' active' : '' ?>" href="<?= base_url('contact.php?type=quote') ?>"><i class="feather-file-text me-2"></i>Request a quote</a></li>
                        <li class="nav-item"><a class="nav-link<?= !$isQuote ? ' active' : '' ?>" href="<?= base_url('contact.php') ?>"><i class="feather-mail me-2"></i>General enquiry</a></li>
                    </ul>
                    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
                    <form method="post" novalidate>
                        <?= spam_fields() ?>
                        <input type="hidden" name="source" value="<?= $isQuote ? 'quote' : 'contact' ?>">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="c-name">Your name *</label><input id="c-name" name="name" class="form-control" value="<?= e($old['name']) ?>" required maxlength="160" autocomplete="name"></div>
                            <div class="col-md-6"><label class="form-label" for="c-phone">Phone / WhatsApp *</label><input id="c-phone" name="phone" type="tel" class="form-control" value="<?= e($old['phone']) ?>" required maxlength="20" autocomplete="tel"></div>
                            <div class="col-md-6"><label class="form-label" for="c-org">Hospital / Clinic / Company</label><input id="c-org" name="organization" class="form-control" value="<?= e($old['organization']) ?>" maxlength="200" autocomplete="organization"></div>
                            <div class="col-md-6"><label class="form-label" for="c-email">Email</label><input id="c-email" name="email" type="email" class="form-control" value="<?= e($old['email']) ?>" maxlength="160" autocomplete="email"></div>
                            <?php if ($isQuote): ?>
                                <div class="col-md-8"><label class="form-label" for="c-product">Product</label>
                                    <select id="c-product" name="product_id" class="form-select">
                                        <option value="">— Select a product (optional) —</option>
                                        <?php $grp = null; foreach ($products as $pr): ?>
                                            <?php if ($pr['category_name'] !== $grp): ?><?= $grp !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($pr['category_name'] ?: 'Other') ?>"><?php $grp = $pr['category_name']; endif; ?>
                                            <option value="<?= (int) $pr['id'] ?>" <?= (int) $old['product_id'] === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option>
                                        <?php endforeach; ?><?= $grp !== null ? '</optgroup>' : '' ?>
                                    </select>
                                </div>
                                <div class="col-md-4"><label class="form-label" for="c-qty">Quantity</label><input id="c-qty" name="quantity" type="number" min="1" class="form-control" value="<?= e($old['quantity']) ?>"></div>
                            <?php endif; ?>
                            <div class="col-md-<?= $isQuote ? '4' : '6' ?>"><label class="form-label" for="c-city">City</label><input id="c-city" name="city" class="form-control" value="<?= e($old['city']) ?>" maxlength="80"></div>
                            <div class="col-md-<?= $isQuote ? '8' : '6' ?>"><label class="form-label" for="c-subject"><?= $isQuote ? 'Other products needed' : 'Subject' ?></label><input id="c-subject" name="subject" class="form-control" value="<?= e($old['subject']) ?>" maxlength="200"></div>
                            <div class="col-12"><label class="form-label" for="c-msg"><?= $isQuote ? 'Requirements' : 'Message' ?></label><textarea id="c-msg" name="message" class="form-control" rows="5" maxlength="5000" placeholder="<?= $isQuote ? 'List the equipment, quantities, preferred brands, delivery location and timeline…' : 'How can we help?' ?>"><?= e($old['message']) ?></textarea></div>
                            <div class="col-12"><button class="btn btn-primary btn-lg vh-btn px-5"><?= $isQuote ? 'Send quote request' : 'Send message' ?></button></div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="vh-contact-card">
                    <h2 class="vh-h3 text-white">Reach us directly</h2>
                    <ul class="list-unstyled mt-4">
                        <?php if (setting('phone')): ?><li><i class="feather-phone"></i><div><small>Call</small><a href="<?= e(tel_link(setting('phone'))) ?>"><?= e(setting('phone')) ?></a><?= setting('phone2') ? '<br><a href="' . e(tel_link(setting('phone2'))) . '">' . e(setting('phone2')) . '</a>' : '' ?></div></li><?php endif; ?>
                        <?php if (whatsapp_link()): ?><li><i class="feather-message-circle"></i><div><small>WhatsApp</small><a href="<?= e(whatsapp_link('Hello, I have an enquiry.')) ?>" target="_blank" rel="noopener">Chat with us</a></div></li><?php endif; ?>
                        <?php if (setting('email')): ?><li><i class="feather-mail"></i><div><small>Email</small><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></div></li><?php endif; ?>
                        <?php if (setting('address')): ?><li><i class="feather-map-pin"></i><div><small>Address</small><?= nl2br(e(setting('address'))) ?><?= setting('city') ? '<br>' . e(setting('city')) : '' ?></div></li><?php endif; ?>
                        <?php if (setting('business_hours')): ?><li><i class="feather-clock"></i><div><small>Business hours</small><?= e(setting('business_hours')) ?></div></li><?php endif; ?>
                    </ul>
                    <?php if (!setting('phone') && !setting('email') && !setting('address')): ?>
                        <p class="text-white-50">Send us a message using the form and our team will get back to you.</p>
                    <?php endif; ?>
                </div>
                <?php if ($mapOk): ?>
                    <div class="vh-map mt-4"><iframe src="<?= e($mapUrl) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/site-footer.php';
