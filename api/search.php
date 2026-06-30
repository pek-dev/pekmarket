<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'products' => []]);
    exit;
}

$searchTerm = "%$query%";

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.slug, p.price, pi.image_path
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    WHERE p.is_active = 1 AND (p.name LIKE ? OR p.description LIKE ?)
    ORDER BY p.sales_count DESC, p.views_count DESC
    LIMIT 6
");
$stmt->execute([$searchTerm, $searchTerm]);
$products = $stmt->fetchAll();

$results = [];
foreach ($products as $p) {
    $results[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'slug' => $p['slug'],
        'price' => $p['price'],
        'price_formatted' => formatPrice($p['price']),
        'image' => $p['image_path'] ?? 'https://via.placeholder.com/100'
    ];
}

echo json_encode(['success' => true, 'products' => $results]);