<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$featured = $pdo->query("
    SELECT p.*, pi.image_path 
    FROM products p 
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    WHERE p.is_active = 1 AND p.is_featured = 1
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$stats = [
    'products' => $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn(),
    'sellers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('seller', 'admin')")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PekDev Market - Marketplace du Burundi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#1e40af', secondary: '#f97316' }, fontFamily: { sans: ['Inter', 'sans-serif'] } } }
        }
    </script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="<?= BASE_URL ?>/" class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-xl">P</span>
                    </div>
                    <div>
                        <span class="text-xl font-bold text-blue-600 dark:text-white">PekDev</span>
                        <span class="text-xs text-orange-500 block">Market</span>
                    </div>
                </a>
                <form action="<?= BASE_URL ?>/search.php" method="GET" class="hidden md:flex flex-1 max-w-xl mx-8">
                    <div class="relative w-full">
                        <input type="text" name="q" class="w-full pl-12 pr-4 py-2 border-2 border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-white rounded-full focus:border-blue-600 focus:outline-none" placeholder="Rechercher...">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                </form>
                <div class="flex items-center gap-2">
                    <a href="<?= BASE_URL ?>/cart.php" class="p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        <i class="fas fa-shopping-cart text-xl"></i>
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>/profile.php" class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                            </div>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 text-sm">Connexion</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-blue-50 to-orange-50 dark:from-gray-800 dark:to-gray-900 py-12 md:py-20">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-6xl font-bold text-gray-800 dark:text-white mb-4">
                Bienvenue sur <span class="text-blue-600">PekDev</span> <span class="text-orange-500">Market</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-600 dark:text-gray-300 mb-8">La plus grande marketplace du Burundi 🇧🇮</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= BASE_URL ?>/products.php" class="px-8 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 shadow-lg">
                    <i class="fas fa-shopping-bag mr-2"></i>Acheter
                </a>
                <a href="<?= BASE_URL ?>/register.php?role=seller" class="px-8 py-3 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600 shadow-lg">
                    <i class="fas fa-store mr-2"></i>Vendre
                </a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-12 max-w-3xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
                    <p class="text-2xl md:text-3xl font-bold text-blue-600"><?= $stats['products'] ?>+</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Produits</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
                    <p class="text-2xl md:text-3xl font-bold text-orange-500"><?= $stats['sellers'] ?>+</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Vendeurs</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
                    <p class="text-2xl md:text-3xl font-bold text-green-600">18</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Provinces</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
                    <p class="text-2xl md:text-3xl font-bold text-purple-600">24/7</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Support</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Catégories -->
    <section class="py-12 bg-white dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white mb-8 text-center">Catégories</h2>
            <div class="grid grid-cols-3 md:grid-cols-7 gap-4">
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= BASE_URL ?>/category.php?slug=<?= $cat['slug'] ?>" class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 text-center hover:shadow-xl transition group">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-xl flex items-center justify-center mx-auto mb-2 group-hover:scale-110 transition">
                            <i class="<?= $cat['icon'] ?> text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-xs md:text-sm font-medium text-gray-700 dark:text-gray-200"><?= clean($cat['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Produits -->
    <section class="py-12 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white mb-8">Produits en vedette</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($featured as $p): ?>
                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $p['slug'] ?>" class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow hover:shadow-xl transition group">
                        <div class="relative overflow-hidden">
                            <img src="<?= $p['image_path'] ?? 'https://via.placeholder.com/300' ?>" class="w-full h-40 object-cover group-hover:scale-105 transition" alt="">
                            <?php if ($p['old_price']): ?>
                                <span class="absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded">-<?= calculateDiscount($p['price'], $p['old_price']) ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-3">
                            <h3 class="font-semibold text-sm text-gray-800 dark:text-white line-clamp-2"><?= clean($p['name']) ?></h3>
                            <p class="text-blue-600 font-bold mt-2"><?= formatPrice($p['price']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-8">
                <a href="<?= BASE_URL ?>/products.php" class="inline-block px-8 py-3 border-2 border-blue-600 text-blue-600 rounded-lg font-semibold hover:bg-blue-600 hover:text-white">
                    Voir tous les produits <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p>&copy; <?= date('Y') ?> PekDev Market. Tous droits réservés.</p>
        </div>
    </footer>
</body>
</html>