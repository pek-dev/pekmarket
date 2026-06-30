<?php
require_once __DIR__ . '/config/bootstrap.php';

$pageTitle = 'Accueil';
$pageDescription = 'La plus grande marketplace du Burundi. Achetez et vendez facilement.';

// Produits en vedette
try {
    $featuredStmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, c.icon as category_icon, c.color as category_color,
               u.first_name as seller_first, u.last_name as seller_last, u.id as seller_id
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.seller_id = u.id
        WHERE p.is_active = 1 AND p.is_featured = 1
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $featuredStmt->execute();
    $featuredProducts = $featuredStmt->fetchAll();
} catch (Exception $e) {
    $featuredProducts = [];
}

// Nouveaux produits
try {
    $newStmt = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND p.is_new = 1
        ORDER BY p.created_at DESC
        LIMIT 4
    ");
    $newProducts = $newStmt->fetchAll();
} catch (Exception $e) {
    $newProducts = [];
}

// Statistiques
try {
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM products WHERE is_active = 1) as products,
            (SELECT COUNT(*) FROM users WHERE role IN ('seller', 'admin') AND is_active = 1) as sellers,
            (SELECT COUNT(DISTINCT province) FROM users WHERE province IS NOT NULL) as provinces,
            (SELECT COUNT(*) FROM users WHERE role = 'customer') as customers
    ");
    $stats = $statsStmt->fetch();
} catch (Exception $e) {
    $stats = ['products' => 0, 'sellers' => 0, 'provinces' => 0, 'customers' => 0];
}

// Catégories
try {
    $categoriesStmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 7");
    $categories = $categoriesStmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Meilleurs vendeurs
try {
    $topSellersStmt = $pdo->query("
        SELECT u.*, 
               COUNT(p.id) as products_count,
               AVG(r.rating) as avg_rating
        FROM users u
        LEFT JOIN products p ON u.id = p.seller_id AND p.is_active = 1
        LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
        WHERE u.role = 'seller' AND u.is_active = 1
        GROUP BY u.id
        HAVING products_count > 0
        ORDER BY products_count DESC
        LIMIT 4
    ");
    $topSellers = $topSellersStmt->fetchAll();
} catch (Exception $e) {
    $topSellers = [];
}

// Meilleurs produits (par ventes)
try {
    $bestSellersStmt = $pdo->query("
        SELECT p.*, 
               c.name as category_name,
               u.first_name as seller_first,
               u.last_name as seller_last
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.seller_id = u.id
        WHERE p.is_active = 1
        ORDER BY p.sales_count DESC
        LIMIT 4
    ");
    $bestSellers = $bestSellersStmt->fetchAll();
} catch (Exception $e) {
    $bestSellers = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative bg-white dark:bg-gray-800 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-16">
        <div class="relative rounded-2xl md:rounded-3xl overflow-hidden bg-gradient-to-br from-blue-50 to-orange-50 dark:from-gray-700 dark:to-gray-800 shadow-2xl">
            <div class="grid md:grid-cols-2 gap-6 md:gap-8 items-center p-6 md:p-12">
                <div class="space-y-4 md:space-y-6 order-2 md:order-1">
                    <div class="inline-flex items-center gap-2 bg-white dark:bg-gray-600 px-4 py-2 rounded-full shadow-md">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span class="text-xs md:text-sm font-medium text-gray-700 dark:text-gray-200">+<?= number_format($stats['products']) ?> produits disponibles</span>
                    </div>
                    <div>
                        <p class="text-blue-600 dark:text-blue-400 font-semibold text-base md:text-lg mb-2">Bienvenue sur</p>
                        <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold leading-tight">
                            <span class="text-blue-600 dark:text-white">PekDev</span>
                            <span class="text-orange-500"> Market</span>
                        </h1>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300 text-base md:text-lg">
                        La plus grande marketplace du Burundi.<br>
                        Achetez et vendez facilement près de chez vous.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="<?= BASE_URL ?>/products.php" class="px-6 md:px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold shadow-lg flex items-center justify-center gap-2 transition">
                            <i class="fas fa-shopping-bag"></i> Acheter maintenant
                        </a>
                        <a href="<?= BASE_URL ?>/register.php?role=seller" class="px-6 md:px-8 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 font-semibold shadow-lg flex items-center justify-center gap-2 transition">
                            <i class="fas fa-store"></i> Vendre un produit
                        </a>
                    </div>
                    
                    <div class="flex flex-wrap gap-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex items-center gap-2 bg-white/50 dark:bg-gray-700/50 px-3 py-2 rounded-lg">
                            <i class="fas fa-shield-alt text-green-500"></i>
                            <span class="text-xs md:text-sm text-gray-700 dark:text-gray-200 font-medium">Paiement sécurisé</span>
                        </div>
                        <div class="flex items-center gap-2 bg-white/50 dark:bg-gray-700/50 px-3 py-2 rounded-lg">
                            <i class="fas fa-truck text-blue-500"></i>
                            <span class="text-xs md:text-sm text-gray-700 dark:text-gray-200 font-medium">Livraison rapide</span>
                        </div>
                        <div class="flex items-center gap-2 bg-white/50 dark:bg-gray-700/50 px-3 py-2 rounded-lg">
                            <i class="fas fa-headset text-purple-500"></i>
                            <span class="text-xs md:text-sm text-gray-700 dark:text-gray-200 font-medium">Support 24/7</span>
                        </div>
                    </div>
                </div>

                <div class="relative order-1 md:order-2">
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl">
                        <img src="https://images.unsplash.com/photo-1488459716781-31db52582fe9?w=800&h=600&fit=crop" 
                             alt="Market" 
                             class="w-full h-64 sm:h-80 md:h-96 object-cover hover:scale-105 transition duration-700">
                    </div>
                    
                    <div class="absolute -left-2 md:-left-4 top-1/4 bg-white dark:bg-gray-700 rounded-xl shadow-xl p-3 md:p-4 flex items-center gap-3 animate-bounce">
                        <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shopping-bag text-blue-600 text-lg md:text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xl md:text-2xl font-bold text-blue-600 dark:text-white">+ <?= number_format($stats['products']) ?></p>
                            <p class="text-xs md:text-sm text-gray-600 dark:text-gray-300">Produits</p>
                        </div>
                    </div>

                    <div class="absolute -right-2 md:-right-4 bottom-1/4 bg-white dark:bg-gray-700 rounded-xl shadow-xl p-3 md:p-4 flex items-center gap-3 animate-bounce" style="animation-delay: 1s;">
                        <div class="w-10 h-10 md:w-12 md:h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-green-600 text-lg md:text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xl md:text-2xl font-bold text-blue-600 dark:text-white">+ <?= number_format($stats['sellers']) ?></p>
                            <p class="text-xs md:text-sm text-gray-600 dark:text-gray-300">Vendeurs</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-8 md:py-12 bg-white dark:bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8">
            <div class="text-center p-4 md:p-6 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-gray-700 dark:to-gray-600 rounded-xl border border-blue-200 dark:border-gray-600">
                <p class="text-2xl md:text-4xl font-bold text-blue-600"><?= number_format($stats['products']) ?>+</p>
                <p class="text-sm md:text-base text-gray-600 dark:text-gray-300 mt-1">Produits</p>
            </div>
            <div class="text-center p-4 md:p-6 bg-gradient-to-br from-orange-50 to-orange-100 dark:from-gray-700 dark:to-gray-600 rounded-xl border border-orange-200 dark:border-gray-600">
                <p class="text-2xl md:text-4xl font-bold text-orange-500"><?= number_format($stats['sellers']) ?>+</p>
                <p class="text-sm md:text-base text-gray-600 dark:text-gray-300 mt-1">Vendeurs</p>
            </div>
            <div class="text-center p-4 md:p-6 bg-gradient-to-br from-green-50 to-green-100 dark:from-gray-700 dark:to-gray-600 rounded-xl border border-green-200 dark:border-gray-600">
                <p class="text-2xl md:text-4xl font-bold text-green-600"><?= $stats['provinces'] ?></p>
                <p class="text-sm md:text-base text-gray-600 dark:text-gray-300 mt-1">Provinces</p>
            </div>
            <div class="text-center p-4 md:p-6 bg-gradient-to-br from-purple-50 to-purple-100 dark:from-gray-700 dark:to-gray-600 rounded-xl border border-purple-200 dark:border-gray-600">
                <p class="text-2xl md:text-4xl font-bold text-purple-600"><?= number_format($stats['customers']) ?>+</p>
                <p class="text-sm md:text-base text-gray-600 dark:text-gray-300 mt-1">Clients</p>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6 md:mb-8">
            <div>
                <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 dark:text-white">Catégories populaires</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Explorez nos différentes catégories</p>
            </div>
            <a href="<?= BASE_URL ?>/categories.php" class="text-blue-600 hover:text-orange-500 font-medium flex items-center gap-1 text-sm md:text-base">
                Voir tout <i class="fas fa-chevron-right text-xs"></i>
            </a>
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-7 gap-3 md:gap-4">
            <?php foreach ($categories as $cat): ?>
                <a href="<?= BASE_URL ?>/category.php?slug=<?= $cat['slug'] ?>" class="group cursor-pointer">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 md:p-6 flex flex-col items-center gap-2 md:gap-3 hover:shadow-xl border border-gray-100 dark:border-gray-700 transition">
                        <div class="w-12 h-12 md:w-14 md:h-14 bg-<?= $cat['color'] ?>-100 dark:bg-<?= $cat['color'] ?>-900 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                            <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500 text-xl md:text-2xl"></i>
                        </div>
                        <span class="text-xs md:text-sm font-medium text-gray-700 dark:text-gray-200 text-center"><?= clean($cat['name']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="py-8 md:py-12 bg-white dark:bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6 md:mb-8">
            <div>
                <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 dark:text-white">Produits en vedette</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Découvrez nos meilleures offres</p>
            </div>
            <a href="<?= BASE_URL ?>/products.php" class="text-blue-600 hover:text-orange-500 font-medium flex items-center gap-1 text-sm md:text-base">
                Voir tout <i class="fas fa-chevron-right text-xs"></i>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-6">
            <?php foreach ($featuredProducts as $product): 
                $image = getProductImage($product['id'], $pdo);
                $discount = calculateDiscount($product['price'], $product['old_price']);
            ?>
                <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm hover:shadow-2xl transition duration-300 overflow-hidden group border border-gray-100 dark:border-gray-600">
                    <div class="relative overflow-hidden">
                        <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
                            <img src="<?= $image ?>" 
                                 alt="<?= clean($product['name']) ?>" 
                                 class="w-full h-36 sm:h-44 md:h-48 object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                        </a>
                        <?php if ($product['is_new']): ?>
                            <span class="absolute top-2 left-2 bg-orange-500 text-white text-[10px] md:text-xs px-2 py-1 rounded font-semibold">Nouveau</span>
                        <?php endif; ?>
                        <?php if ($discount > 0): ?>
                            <span class="absolute top-2 <?= $product['is_new'] ? 'left-20' : 'left-2' ?> bg-red-500 text-white text-[10px] md:text-xs px-2 py-1 rounded font-semibold">-<?= $discount ?>%</span>
                        <?php endif; ?>
                        
                        <?php if (isLoggedIn()): ?>
                            <button class="absolute top-2 right-2 w-7 h-7 md:w-8 md:h-8 bg-white dark:bg-gray-800 rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-red-500 transition" 
                                    onclick="toggleFavorite(<?= $product['id'] ?>, this)">
                                <i class="<?= isFavorite($_SESSION['user_id'], $product['id'], $pdo) ? 'fas text-red-500' : 'far' ?> fa-heart text-xs md:text-sm"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 md:p-4">
                        <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>" class="block">
                            <h3 class="font-semibold text-gray-800 dark:text-white mb-1 text-sm md:text-base line-clamp-2 hover:text-blue-600 transition"><?= clean($product['name']) ?></h3>
                        </a>
                        <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <i class="fas fa-map-marker-alt text-orange-500"></i>
                            <?= clean($product['city'] ?? $product['province']) ?>
                        </div>
                        <?php if ($product['rating_count'] > 0): ?>
                            <div class="flex items-center gap-1 mb-2">
                                <?= renderStars($product['rating_avg']) ?>
                                <span class="text-xs text-gray-500">(<?= $product['rating_count'] ?>)</span>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-base md:text-lg font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                                <?php if ($product['old_price']): ?>
                                    <span class="text-xs text-gray-400 line-through ml-1"><?= formatPrice($product['old_price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="w-8 h-8 md:w-9 md:h-9 bg-blue-50 dark:bg-blue-900 rounded-full flex items-center justify-center text-blue-600 hover:bg-blue-600 hover:text-white transition" 
                                    onclick="addToCart(<?= $product['id'] ?>)">
                                <i class="fas fa-shopping-cart text-xs md:text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Best Sellers Products -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6 md:mb-8">
            <div>
                <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 dark:text-white">Meilleures ventes</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Les produits les plus populaires</p>
            </div>
            <a href="<?= BASE_URL ?>/products.php?sort=sales" class="text-blue-600 hover:text-orange-500 font-medium flex items-center gap-1 text-sm md:text-base">
                Voir tout <i class="fas fa-chevron-right text-xs"></i>
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            <?php foreach ($bestSellers as $product): 
                $image = getProductImage($product['id'], $pdo);
            ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-2xl transition duration-300 overflow-hidden group border border-gray-100 dark:border-gray-700">
                    <div class="relative overflow-hidden">
                        <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
                            <img src="<?= $image ?>" alt="<?= clean($product['name']) ?>" class="w-full h-40 md:h-48 object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                        </a>
                        <?php if ($product['sales_count'] > 50): ?>
                            <span class="absolute top-2 left-2 bg-orange-500 text-white text-[10px] md:text-xs px-2 py-1 rounded font-semibold">
                                <i class="fas fa-fire mr-1"></i>Populaire
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 md:p-4">
                        <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>" class="block">
                            <h3 class="font-semibold text-gray-800 dark:text-white mb-1 text-sm md:text-base line-clamp-2 hover:text-blue-600 transition"><?= clean($product['name']) ?></h3>
                        </a>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <i class="fas fa-user text-orange-500 mr-1"></i>
                            <?= clean($product['seller_first'] . ' ' . $product['seller_last']) ?>
                        </p>
                        <?php if ($product['rating_count'] > 0): ?>
                            <div class="flex items-center gap-1 mb-2">
                                <?= renderStars($product['rating_avg']) ?>
                                <span class="text-xs text-gray-500">(<?= $product['rating_count'] ?>)</span>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between">
                            <span class="text-base md:text-lg font-bold text-blue-600"><?= formatPrice($product['price']) ?></span>
                            <button class="w-8 h-8 md:w-9 md:h-9 bg-blue-50 dark:bg-blue-900 rounded-full flex items-center justify-center text-blue-600 hover:bg-blue-600 hover:text-white transition" 
                                    onclick="addToCart(<?= $product['id'] ?>)">
                                <i class="fas fa-shopping-cart text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Promo Banners Section -->
<section class="py-8 md:py-12 bg-white dark:bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-4 md:gap-6">
            <div class="relative rounded-2xl overflow-hidden bg-gradient-to-r from-blue-600 to-blue-800 p-6 md:p-10 text-white group cursor-pointer hover:shadow-2xl transition">
                <div class="relative z-10">
                    <span class="bg-white/20 text-xs px-3 py-1 rounded-full">PROMO</span>
                    <h3 class="text-2xl md:text-3xl font-bold mt-3">Électronique</h3>
                    <p class="text-blue-100 mt-2">Jusqu'à -30% sur les smartphones</p>
                    <a href="<?= BASE_URL ?>/category.php?slug=electronique" class="mt-4 inline-block px-6 py-2 bg-white text-blue-600 rounded-lg font-semibold hover:bg-gray-100">
                        Découvrir <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
                <i class="fas fa-mobile-alt absolute -right-4 -bottom-4 text-9xl text-white/10 group-hover:scale-110 transition"></i>
            </div>
            <div class="relative rounded-2xl overflow-hidden bg-gradient-to-r from-orange-500 to-red-500 p-6 md:p-10 text-white group cursor-pointer hover:shadow-2xl transition">
                <div class="relative z-10">
                    <span class="bg-white/20 text-xs px-3 py-1 rounded-full">NOUVEAU</span>
                    <h3 class="text-2xl md:text-3xl font-bold mt-3">Mode & Beauté</h3>
                    <p class="text-orange-100 mt-2">Nouvelle collection disponible</p>
                    <a href="<?= BASE_URL ?>/category.php?slug=mode-beaute" class="mt-4 inline-block px-6 py-2 bg-white text-orange-500 rounded-lg font-semibold hover:bg-gray-100">
                        Explorer <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
                <i class="fas fa-tshirt absolute -right-4 -bottom-4 text-9xl text-white/10 group-hover:scale-110 transition"></i>
            </div>
        </div>
    </div>
</section>

<!-- Top Sellers Section -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6 md:mb-8">
            <div>
                <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 dark:text-white">Nos meilleurs vendeurs</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Découvrez les boutiques populaires</p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
            <?php foreach ($topSellers as $seller): ?>
                <a href="<?= BASE_URL ?>/shop.php?seller=<?= $seller['id'] ?>" class="group">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center hover:shadow-xl transition border border-gray-100 dark:border-gray-700">
                        <div class="w-20 h-20 mx-auto bg-gradient-to-br from-blue-600 to-orange-500 rounded-full flex items-center justify-center text-white text-2xl font-bold mb-4 group-hover:scale-110 transition">
                            <?= strtoupper(substr($seller['first_name'], 0, 1) . substr($seller['last_name'], 0, 1)) ?>
                        </div>
                        <h3 class="font-semibold text-gray-800 dark:text-white mb-1"><?= clean($seller['first_name'] . ' ' . $seller['last_name']) ?></h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                            <i class="fas fa-map-marker-alt text-orange-500 mr-1"></i>
                            <?= clean($seller['city'] ?? $seller['province']) ?>
                        </p>
                        <div class="flex justify-center gap-4 text-sm">
                            <div class="text-center">
                                <p class="font-bold text-blue-600"><?= $seller['products_count'] ?></p>
                                <p class="text-xs text-gray-500">Produits</p>
                            </div>
                            <?php if ($seller['avg_rating']): ?>
                            <div class="text-center">
                                <p class="font-bold text-blue-600"><?= number_format($seller['avg_rating'], 1) ?></p>
                                <p class="text-xs text-gray-500">Note</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="py-8 md:py-12 bg-gradient-to-r from-blue-600 to-orange-500 relative overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-40 h-40 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-60 h-60 bg-white rounded-full translate-x-1/2 translate-y-1/2"></div>
    </div>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <div class="inline-block mb-4">
            <i class="fas fa-envelope-open-text text-white text-5xl"></i>
        </div>
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-3">Restez informé de nos offres</h2>
        <p class="text-white/90 mb-6">Inscrivez-vous à notre newsletter et recevez les meilleures offres</p>
        <form class="flex flex-col sm:flex-row gap-3 max-w-lg mx-auto" action="<?= BASE_URL ?>/newsletter.php" method="POST">
            <input type="email" name="email" required class="flex-1 px-6 py-3 rounded-lg focus:outline-none focus:ring-4 focus:ring-white/30 text-gray-800" placeholder="Votre adresse email">
            <button type="submit" class="px-8 py-3 bg-gray-900 text-white rounded-lg font-semibold hover:bg-gray-800 transition">
                <i class="fas fa-paper-plane mr-2"></i>S'inscrire
            </button>
        </form>
    </div>
</section>

<!-- Trust Badges -->
<section class="py-8 md:py-12 bg-white dark:bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mb-3">
                    <i class="fas fa-shipping-fast text-blue-500 text-2xl"></i>
                </div>
                <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Livraison rapide</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Dans tout le Burundi</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mb-3">
                    <i class="fas fa-shield-alt text-green-500 text-2xl"></i>
                </div>
                <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Paiement sécurisé</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Mobile Money, Cash</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center mb-3">
                    <i class="fas fa-headset text-purple-500 text-2xl"></i>
                </div>
                <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Support 24/7</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Assistance disponible</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center mb-3">
                    <i class="fas fa-undo text-orange-500 text-2xl"></i>
                </div>
                <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Retours faciles</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Sous 30 jours</p>
            </div>
        </div>
    </div>
</section>

<script>
// ==========================================
// FONCTION AJOUTER AU PANIER
// ==========================================
function addToCart(productId) {
    <?php if (!isLoggedIn()): ?>
        alert('Vous devez être connecté pour ajouter au panier !');
        window.location.href = '<?= BASE_URL ?>/login.php';
        return;
    <?php endif; ?>
    
    fetch('<?= BASE_URL ?>/api/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cartCount = document.getElementById('cartCount');
            if (cartCount) {
                cartCount.textContent = parseInt(cartCount.textContent || 0) + 1;
            }
            showToast('Produit ajouté au panier !', 'success');
        } else {
            showToast(data.message || 'Erreur lors de l\'ajout', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erreur de connexion', 'error');
    });
}

// ==========================================
// FONCTION FAVORIS
// ==========================================
function toggleFavorite(productId, button) {
    <?php if (!isLoggedIn()): ?>
        alert('Vous devez être connecté pour ajouter aux favoris !');
        window.location.href = '<?= BASE_URL ?>/login.php';
        return;
    <?php endif; ?>
    
    fetch('<?= BASE_URL ?>/api/toggle-favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const icon = button.querySelector('i');
            if (data.action === 'added') {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-red-500');
                showToast('Ajouté aux favoris ❤️', 'success');
            } else {
                icon.classList.remove('fas', 'text-red-500');
                icon.classList.add('far');
                showToast('Retiré des favoris', 'info');
            }
        } else {
            showToast(data.message || 'Erreur', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erreur de connexion', 'error');
    });
}

// ==========================================
// SYSTÈME DE TOASTS (NOTIFICATIONS)
// ==========================================
function showToast(message, type = 'info') {
    // Supprimer les anciens toasts
    const existing = document.getElementById('toast-container');
    if (existing) existing.remove();
    
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.id = 'toast-container';
    toast.className = 'fixed top-20 right-4 z-50';
    toast.innerHTML = `
        <div class="${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 animate-slide-down">
            <i class="fas ${icons[type]}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 hover:opacity-70">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-suppression après 3 secondes
    setTimeout(() => {
        if (document.getElementById('toast-container')) {
            toast.remove();
        }
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>