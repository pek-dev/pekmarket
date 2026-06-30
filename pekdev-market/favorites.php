<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$favorites = $pdo->prepare("SELECT p.*, pi.image_path FROM favorites f JOIN products p ON f.product_id = p.id LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE f.user_id = ?");
$favorites->execute([$_SESSION['user_id']]);
$favorites = $favorites->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Favoris - PekDev Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#1e40af' } } } }</script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-6"><i class="fas fa-heart text-red-500 mr-2"></i>Mes Favoris</h1>
        <?php if (empty($favorites)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 text-center">
                <i class="far fa-heart text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Aucun favori</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($favorites as $p): ?>
                    <a href="product.php?slug=<?= $p['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow hover:shadow-xl transition">
                        <img src="<?= $p['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover">
                        <div class="p-3">
                            <h3 class="font-semibold text-sm"><?= clean($p['name']) ?></h3>
                            <p class="text-blue-600 font-bold mt-2"><?= formatPrice($p['price']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>