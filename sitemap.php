<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';


header('Content-Type: application/xml');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= BASE_URL ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= BASE_URL ?>/products.php</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= BASE_URL ?>/promotions.php</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= BASE_URL ?>/about.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?= BASE_URL ?>/contact.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <?php
    $categories = $pdo->query("SELECT slug, updated_at FROM categories WHERE is_active = 1")->fetchAll();
    foreach ($categories as $cat): ?>
        <url>
            <loc><?= BASE_URL ?>/category.php?slug=<?= $cat['slug'] ?></loc>
            <changefreq>weekly</changefreq>
            <priority>0.7</priority>
        </url>
    <?php endforeach; ?>
    <?php
    $products = $pdo->query("SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1000")->fetchAll();
    foreach ($products as $p): ?>
        <url>
            <loc><?= BASE_URL ?>/product.php?slug=<?= $p['slug'] ?></loc>
            <lastmod><?= date('Y-m-d', strtotime($p['updated_at'])) ?></lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.6</priority>
        </url>
    <?php endforeach; ?>
</urlset>