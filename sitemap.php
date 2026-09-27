<?php
/** XML sitemap for search engines: /sitemap.php */
require __DIR__ . '/includes/bootstrap.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$root = $scheme . '://' . preg_replace('/[^a-z0-9.:-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$urls = [
    [base_url('index.php'), null, '1.0'],
    [base_url('products.php'), null, '0.9'],
    [base_url('service.php'), null, '0.7'],
    [base_url('about.php'), null, '0.5'],
    [base_url('contact.php'), null, '0.6'],
];
foreach (q_all('SELECT slug FROM categories WHERE is_active = 1') as $c) {
    $urls[] = [base_url('products.php?category=' . rawurlencode($c['slug'])), null, '0.8'];
}
foreach (q_all('SELECT slug, updated_at FROM products WHERE is_active = 1') as $p) {
    $urls[] = [base_url('product.php?slug=' . rawurlencode($p['slug'])), substr($p['updated_at'], 0, 10), '0.7'];
}
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $prio]) {
    echo '  <url><loc>' . htmlspecialchars($root . $loc, ENT_XML1) . '</loc>' . ($mod ? "<lastmod>$mod</lastmod>" : '') . "<priority>$prio</priority></url>\n";
}
echo '</urlset>';
