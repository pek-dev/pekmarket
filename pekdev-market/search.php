<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$products = [];
if (!empty($q)) {
    $stmt = $pdo->prepare("SELECT p.*, pi.image_path FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE p.is_active = 1 AND (p.name LIKE ? OR p.description LIKE ?) ORDER BY p.sales_count DESC LIMIT 20");
    $stmt->execute(["%$q%", "%$q%"]);
    $products = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#1e40af' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6">Recherche : "<?= clean($q) ?>"</h1>
        <p class="text-gray-500 mb-6"><?= count($products) ?> résultat(s)</p>
        <?php if (empty($products)): ?>
            <div class="bg-white rounded-xl p-12 text-center"><i class="fas fa-search text-6xl text-gray-300 mb-4"></i><p>Aucun résultat</p></div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($products as $p): ?>
                    <a href="product.php?slug=<?= $p['slug'] ?>" class="bg-white rounded-xl overflow-hidden shadow hover:shadow-xl transition">
                        <img src="<?= $p['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover">
                        <div class="p-3">
                            <h3 class="font-semibold text-sm line-clamp-2"><?= clean($p['name']) ?></h3>
                            <p class="text-blue-600 font-bold mt-2"><?= formatPrice($p['price']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>