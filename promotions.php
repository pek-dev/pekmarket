<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/includes/header.php';




$pageTitle = 'Promotions & Offres Spéciales';
$pageDescription = 'Découvrez les meilleures promotions et réductions sur PekDev Market. Économisez sur vos achats !';

// Paramètres de filtre
$searchQuery = trim($_GET['q'] ?? '');
$categorySlug = $_GET['category'] ?? '';
$minDiscount = intval($_GET['min_discount'] ?? 0); // Pourcentage de réduction minimum
$maxPrice = $_GET['max_price'] ?? '';
$sortBy = $_GET['sort'] ?? 'discount'; // Tri par défaut sur la réduction

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$productsPerPage = 12;
$offset = ($page - 1) * $productsPerPage;

// Construction de la requête SQL
// On ne sélectionne que les produits avec une réduction (old_price > price)
$whereConditions = [
    "p.is_active = 1",
    "p.old_price IS NOT NULL",
    "p.old_price > p.price"
];
$params = [];

if ($searchQuery) {
    $whereConditions[] = "(p.name LIKE ? OR p.short_description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($categorySlug) {
    $whereConditions[] = "c.slug = ?";
    $params[] = $categorySlug;
}

if ($minDiscount > 0) {
    // Calcul du pourcentage de réduction en SQL
    $whereConditions[] = "((p.old_price - p.price) / p.old_price * 100) >= ?";
    $params[] = $minDiscount;
}

if ($maxPrice !== '') {
    $whereConditions[] = "p.price <= ?";
    $params[] = (float)$maxPrice;
}

$whereClause = implode(' AND ', $whereConditions);

// Tri spécifique aux promotions
switch ($sortBy) {
    case 'discount_desc':
        $orderBy = '((p.old_price - p.price) / p.old_price * 100) DESC';
        break;
    case 'price_asc':
        $orderBy = 'p.price ASC';
        break;
    case 'price_desc':
        $orderBy = 'p.price DESC';
        break;
    case 'popular':
        $orderBy = 'p.sales_count DESC';
        break;
    case 'newest':
        $orderBy = 'p.created_at DESC';
        break;
    case 'savings':
        // Tri par montant économisé
        $orderBy = '(p.old_price - p.price) DESC';
        break;
    default:
        $orderBy = '((p.old_price - p.price) / p.old_price * 100) DESC';
        break;
}

// Requête principale
$productsSql = "
    SELECT p.*, 
           c.name as category_name, 
           c.slug as category_slug,
           c.icon as category_icon,
           c.color as category_color,
           u.first_name as seller_first, 
           u.last_name as seller_last,
           u.id as seller_id,
           ((p.old_price - p.price) / p.old_price * 100) as discount_percent,
           (p.old_price - p.price) as savings_amount
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.seller_id = u.id
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";

$productsStmt = $pdo->prepare($productsSql);
$params[] = $productsPerPage;
$params[] = $offset;
$productsStmt->execute($params);
$products = $productsStmt->fetchAll();

// Compter le total
$countSql = "
    SELECT COUNT(*) 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $whereClause
";
$countStmt = $pdo->prepare($countSql);
$countParams = array_slice($params, 0, -2);
$countStmt->execute($countParams);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $productsPerPage);

// Statistiques globales des promotions
$promoStatsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_promos,
        AVG((old_price - price) / old_price * 100) as avg_discount,
        MAX((old_price - price) / old_price * 100) as max_discount,
        SUM(old_price - price) as total_savings
    FROM products 
    WHERE is_active = 1 AND old_price IS NOT NULL AND old_price > price
");
$promoStats = $promoStatsStmt->fetch();

// Catégories avec promotions
$categoriesStmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as promo_count
    FROM categories c
    INNER JOIN products p ON c.id = p.category_id 
    WHERE p.is_active = 1 AND p.old_price IS NOT NULL AND p.old_price > p.price
    GROUP BY c.id
    ORDER BY promo_count DESC
");
$promoCategories = $categoriesStmt->fetchAll();

// Meilleures offres (top 3 par réduction)
$topDealsStmt = $pdo->query("
    SELECT p.*, 
           ((p.old_price - p.price) / p.old_price * 100) as discount_percent
    FROM products p
    WHERE p.is_active = 1 AND p.old_price IS NOT NULL AND p.old_price > p.price
    ORDER BY discount_percent DESC
    LIMIT 3
");
$topDeals = $topDealsStmt->fetchAll();


?>

<!-- Hero Section Promotions -->
<section class="relative bg-gradient-to-br from-red-600 via-orange-500 to-yellow-500 py-12 md:py-16 overflow-hidden">
    <!-- Background decorations -->
    <div class="absolute inset-0 opacity-20">
        <div class="absolute top-10 left-10 w-32 h-32 bg-white rounded-full animate-pulse"></div>
        <div class="absolute bottom-10 right-20 w-48 h-48 bg-white rounded-full animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/3 w-24 h-24 bg-white rounded-full animate-pulse" style="animation-delay: 2s;"></div>
    </div>
    
    <!-- Confetti-like dots -->
    <div class="absolute inset-0 opacity-30">
        <div class="absolute top-20 left-1/4 w-2 h-2 bg-white rounded-full"></div>
        <div class="absolute top-32 right-1/3 w-3 h-3 bg-white rounded-full"></div>
        <div class="absolute bottom-24 left-1/2 w-2 h-2 bg-white rounded-full"></div>
        <div class="absolute top-16 right-1/4 w-3 h-3 bg-white rounded-full"></div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <nav class="text-sm text-white/80 mb-4">
            <a href="<?= BASE_URL ?>" class="hover:text-white transition">Accueil</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white font-medium">Promotions</span>
        </nav>
        
        <div class="flex flex-col md:flex-row items-center gap-8">
            <div class="flex-1 text-white">
                <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full mb-4">
                    <i class="fas fa-fire text-yellow-300 animate-pulse"></i>
                    <span class="text-sm font-semibold">Offres limitées</span>
                </div>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-4 leading-tight">
                    🔥 Promotions <br>
                    <span class="text-yellow-300">& Offres Spéciales</span>
                </h1>
                <p class="text-white/90 text-lg md:text-xl mb-6 max-w-2xl">
                    Jusqu'à <span class="font-bold text-yellow-300 text-2xl"><?= number_format($promoStats['max_discount'] ?? 0, 0) ?>%</span> de réduction sur des milliers de produits. Ne manquez pas ces opportunités !
                </p>
                
                <div class="flex flex-wrap gap-3">
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-3 rounded-xl">
                        <p class="text-xs text-white/80">Offres actives</p>
                        <p class="text-2xl font-bold"><?= number_format($promoStats['total_promos'] ?? 0) ?></p>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-3 rounded-xl">
                        <p class="text-xs text-white/80">Réduction moyenne</p>
                        <p class="text-2xl font-bold"><?= number_format($promoStats['avg_discount'] ?? 0, 0) ?>%</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-3 rounded-xl">
                        <p class="text-xs text-white/80">Économies totales</p>
                        <p class="text-xl font-bold"><?= formatPrice($promoStats['total_savings'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Top Deals Cards -->
            <div class="w-full md:w-96 space-y-3">
                <h3 class="text-white font-bold text-lg mb-3 flex items-center gap-2">
                    <i class="fas fa-trophy text-yellow-300"></i> Top 3 des offres
                </h3>
                <?php foreach ($topDeals as $deal): 
                    $dealImage = getProductImage($deal['id'], $pdo);
                ?>
                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $deal['slug'] ?>" 
                       class="bg-white/95 backdrop-blur-sm rounded-xl p-3 flex items-center gap-3 hover:scale-105 transition shadow-xl">
                        <img src="<?= $dealImage ?>" alt="<?= clean($deal['name']) ?>" class="w-16 h-16 object-cover rounded-lg">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800 text-sm line-clamp-1"><?= clean($deal['name']) ?></p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-red-600 font-bold"><?= formatPrice($deal['price']) ?></span>
                                <span class="text-xs text-gray-400 line-through"><?= formatPrice($deal['old_price']) ?></span>
                            </div>
                        </div>
                        <div class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-lg">
                            -<?= number_format($deal['discount_percent'], 0) ?>%
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Flash Banner -->
<section class="bg-gray-900 text-white py-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <i class="fas fa-bolt text-yellow-400 text-2xl animate-pulse"></i>
                <span class="font-bold text-lg">Flash Deals</span>
                <span class="text-sm text-gray-300">Profitez-en avant qu'il ne soit trop tard !</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-gray-400">Se termine dans :</span>
                <div class="flex gap-2" id="countdown">
                    <div class="bg-red-600 px-3 py-1 rounded font-bold"><span id="hours">23</span>h</div>
                    <div class="bg-red-600 px-3 py-1 rounded font-bold"><span id="minutes">59</span>m</div>
                    <div class="bg-red-600 px-3 py-1 rounded font-bold"><span id="seconds">59</span>s</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Search & Quick Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" action="" class="flex flex-col md:flex-row gap-3">
                <input type="hidden" name="category" value="<?= clean($categorySlug) ?>">
                <input type="hidden" name="min_discount" value="<?= $minDiscount ?>">
                <input type="hidden" name="max_price" value="<?= $maxPrice ?>">
                <input type="hidden" name="sort" value="<?= clean($sortBy) ?>">
                
                <div class="relative flex-1">
                    <input type="text" name="q" value="<?= clean($searchQuery) ?>" 
                           placeholder="Rechercher une promotion..." 
                           class="w-full px-4 py-3 pr-12 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-red-600 text-white rounded-lg flex items-center justify-center hover:bg-red-700 transition">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <select name="min_discount" onchange="this.form.submit()" class="px-4 py-3 border dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 dark:text-white">
                    <option value="0" <?= $minDiscount == 0 ? 'selected' : '' ?>>Toutes réductions</option>
                    <option value="10" <?= $minDiscount == 10 ? 'selected' : '' ?>>-10% et plus</option>
                    <option value="20" <?= $minDiscount == 20 ? 'selected' : '' ?>>-20% et plus</option>
                    <option value="30" <?= $minDiscount == 30 ? 'selected' : '' ?>>-30% et plus</option>
                    <option value="50" <?= $minDiscount == 50 ? 'selected' : '' ?>>-50% et plus</option>
                    <option value="70" <?= $minDiscount == 70 ? 'selected' : '' ?>>-70% et plus</option>
                </select>
            </form>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Mobile Filter Toggle -->
            <button id="mobileFilterBtn" class="lg:hidden mb-4 bg-white dark:bg-gray-800 text-gray-800 dark:text-white px-4 py-3 rounded-lg shadow-sm flex items-center justify-center gap-2 font-semibold">
                <i class="fas fa-filter"></i> Afficher les filtres
            </button>

            <!-- Sidebar Filters -->
            <aside id="filterSidebar" class="hidden lg:block lg:w-1/4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 sticky top-4 space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <i class="fas fa-tags text-red-500"></i> Filtres
                        </h3>
                        <a href="<?= BASE_URL ?>/promotions.php" class="text-xs text-red-500 hover:text-red-700 font-medium">
                            <i class="fas fa-times mr-1"></i> Réinitialiser
                        </a>
                    </div>

                    <!-- Catégories en promo -->
                    <?php if (count($promoCategories) > 0): ?>
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Catégories</h4>
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                            <a href="<?= BASE_URL ?>/promotions.php" 
                               class="flex justify-between items-center px-3 py-2 rounded-lg text-sm transition <?= !$categorySlug ? 'bg-red-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                <span>Toutes</span>
                                <span class="text-xs opacity-75"><?= $totalProducts ?></span>
                            </a>
                            <?php foreach ($promoCategories as $cat): ?>
                                <a href="?category=<?= $cat['slug'] ?>&<?= http_build_query(array_diff_key($_GET, ['category' => '', 'page' => ''])) ?>" 
                                   class="flex justify-between items-center px-3 py-2 rounded-lg text-sm transition <?= $categorySlug == $cat['slug'] ? 'bg-red-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                    <span class="flex items-center gap-2">
                                        <i class="<?= $cat['icon'] ?> text-<?= $cat['color'] ?>-500"></i>
                                        <?= clean($cat['name']) ?>
                                    </span>
                                    <span class="text-xs opacity-75"><?= $cat['promo_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Prix maximum -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-3 text-sm uppercase tracking-wide">Prix maximum</h4>
                        <form method="GET" action="" class="space-y-3">
                            <input type="hidden" name="category" value="<?= clean($categorySlug) ?>">
                            <input type="hidden" name="min_discount" value="<?= $minDiscount ?>">
                            <?php foreach ($_GET as $key => $val): if($key != 'max_price' && $key != 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
                            <?php endif; endforeach; ?>
                            
                            <input type="number" name="max_price" value="<?= $maxPrice ?>" placeholder="Prix max (FBu)" 
                                   class="w-full px-3 py-2 border dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-red-500 dark:bg-gray-700 dark:text-white">
                            <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 text-sm font-semibold transition">
                                Appliquer
                            </button>
                        </form>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-xl p-4 border border-red-200 dark:border-red-800">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-info-circle text-red-500 text-xl mt-1"></i>
                            <div>
                                <h4 class="font-bold text-gray-800 dark:text-white text-sm mb-1">Conseil d'achat</h4>
                                <p class="text-xs text-gray-600 dark:text-gray-300">
                                    Comparez toujours le prix original avec le prix promu. Les meilleures offres sont souvent limitées en stock !
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Products Grid Area -->
            <div class="flex-1">
                <!-- Toolbar -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Trier par:</span>
                        <select onchange="updateSort(this.value)" class="border-0 bg-transparent text-sm font-semibold text-gray-800 dark:text-white focus:ring-0 cursor-pointer">
                            <option value="discount" <?= $sortBy == 'discount' ? 'selected' : '' ?>>% de réduction</option>
                            <option value="savings" <?= $sortBy == 'savings' ? 'selected' : '' ?>>Montant économisé</option>
                            <option value="price_asc" <?= $sortBy == 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                            <option value="price_desc" <?= $sortBy == 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                            <option value="popular" <?= $sortBy == 'popular' ? 'selected' : '' ?>>Popularité</option>
                            <option value="newest" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Plus récents</option>
                        </select>
                    </div>
                    
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-semibold text-red-600"><?= number_format($totalProducts) ?></span> promotions disponibles
                    </div>
                </div>

                <!-- Products Grid -->
                <?php if (count($products) > 0): ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
                        <?php foreach ($products as $product): 
                            $image = getProductImage($product['id'], $pdo);
                            $discount = intval($product['discount_percent']);
                            $savings = $product['savings_amount'];
                        ?>
                            <div class="product-card bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-2xl transition duration-300 overflow-hidden group border-2 border-red-100 dark:border-red-900/30 flex flex-col relative">
                                <!-- Big discount badge -->
                                <div class="absolute top-0 right-0 z-10">
                                    <div class="bg-red-600 text-white font-bold text-lg px-3 py-2 rounded-bl-xl shadow-lg">
                                        -<?= $discount ?>%
                                    </div>
                                </div>
                                
                                <div class="relative overflow-hidden">
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
                                        <img src="<?= $image ?>" 
                                             alt="<?= clean($product['name']) ?>" 
                                             class="w-full h-40 sm:h-48 object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                                    </a>
                                    
                                    <!-- Savings badge -->
                                    <div class="absolute bottom-2 left-2 bg-green-500 text-white text-xs px-2 py-1 rounded font-semibold shadow-md">
                                        <i class="fas fa-piggy-bank mr-1"></i>Économisez <?= formatPrice($savings) ?>
                                    </div>

                                    <?php if ($product['is_new']): ?>
                                        <span class="absolute top-2 left-2 bg-orange-500 text-white text-[10px] md:text-xs px-2 py-1 rounded font-semibold shadow-sm">Nouveau</span>
                                    <?php endif; ?>

                                    <?php if (isLoggedIn()): ?>
                                        <button class="absolute top-14 right-2 w-8 h-8 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-red-500 transition transform hover:scale-110" 
                                                onclick="toggleFavorite(<?= $product['id'] ?>, this)">
                                            <i class="<?= isFavorite($_SESSION['user_id'], $product['id'], $pdo) ? 'fas text-red-500' : 'far' ?> fa-heart text-sm"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-3 md:p-4 flex flex-col flex-1">
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= $product['slug'] ?>" class="block mb-2 flex-1">
                                        <h3 class="font-semibold text-gray-800 dark:text-white text-sm md:text-base line-clamp-2 hover:text-red-600 transition leading-tight">
                                            <?= clean($product['name']) ?>
                                        </h3>
                                    </a>
                                    
                                    <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        <i class="fas fa-map-marker-alt text-orange-500"></i>
                                        <span><?= clean($product['city'] ?? $product['province']) ?></span>
                                    </div>
                                    
                                    <?php if ($product['rating_count'] > 0): ?>
                                        <div class="flex items-center gap-1 mb-3">
                                            <?= renderStars($product['rating_avg']) ?>
                                            <span class="text-xs text-gray-500 ml-1">(<?= $product['rating_count'] ?>)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3"></div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-auto pt-3 border-t border-gray-100 dark:border-gray-700">
                                        <div class="flex items-end gap-2 mb-2">
                                            <span class="text-lg md:text-xl font-bold text-red-600"><?= formatPrice($product['price']) ?></span>
                                            <span class="text-xs text-gray-400 line-through mb-1"><?= formatPrice($product['old_price']) ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-green-600 font-semibold">
                                                <i class="fas fa-arrow-down mr-1"></i><?= formatPrice($savings) ?> de réduction
                                            </span>
                                            <button class="w-9 h-9 bg-red-100 dark:bg-red-900/50 rounded-full flex items-center justify-center text-red-600 hover:bg-red-600 hover:text-white transition transform hover:scale-110" 
                                                    onclick="addToCart(<?= $product['id'] ?>)" title="Ajouter au panier">
                                                <i class="fas fa-shopping-cart text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-10 flex justify-center">
                            <nav class="flex items-center gap-1 bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm">
                                <?php
                                $pagParams = $_GET;
                                unset($pagParams['page']);
                                $baseUrl = BASE_URL . '/promotions.php?' . http_build_query($pagParams);
                                $separator = empty($pagParams) ? '?' : '&';
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl . $separator ?>page=<?= $page - 1 ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="w-10 h-10 flex items-center justify-center rounded-lg text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                
                                if ($start > 1): ?>
                                    <a href="<?= $baseUrl . $separator ?>page=1" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">1</a>
                                    <?php if ($start > 2): ?>
                                        <span class="w-10 h-10 flex items-center justify-center text-gray-400">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <a href="<?= $baseUrl . $separator ?>page=<?= $i ?>" 
                                       class="w-10 h-10 flex items-center justify-center rounded-lg transition font-semibold <?= $i == $page ? 'bg-red-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($end < $totalPages): ?>
                                    <?php if ($end < $totalPages - 1): ?>
                                        <span class="w-10 h-10 flex items-center justify-center text-gray-400">...</span>
                                    <?php endif; ?>
                                    <a href="<?= $baseUrl . $separator ?>page=<?= $totalPages ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition"><?= $totalPages ?></a>
                                <?php endif; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl . $separator ?>page=<?= $page + 1 ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="w-10 h-10 flex items-center justify-center rounded-lg text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-12 text-center">
                        <div class="w-24 h-24 mx-auto bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center mb-6">
                            <i class="fas fa-tag-slash text-4xl text-red-400"></i>
                        </div>
                        <h3 class="text-xl md:text-2xl font-bold text-gray-800 dark:text-white mb-2">Aucune promotion trouvée</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
                            Il n'y a actuellement aucune promotion correspondant à vos critères. Revenez bientôt pour découvrir de nouvelles offres !
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="<?= BASE_URL ?>/promotions.php" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 font-semibold transition">
                                <i class="fas fa-undo mr-2"></i>Voir toutes les promotions
                            </a>
                            <a href="<?= BASE_URL ?>/products.php" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white px-6 py-3 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold transition">
                                Voir tous les produits
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter Promo -->
<section class="py-8 md:py-12 bg-gradient-to-r from-red-600 to-orange-500 relative overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-40 h-40 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-60 h-60 bg-white rounded-full translate-x-1/2 translate-y-1/2"></div>
    </div>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <i class="fas fa-envelope-open-text text-white text-5xl mb-4"></i>
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-3">Ne manquez aucune promotion !</h2>
        <p class="text-white/90 mb-6">Inscrivez-vous pour recevoir les meilleures offres en exclusivité</p>
        <form class="flex flex-col sm:flex-row gap-3 max-w-lg mx-auto" onsubmit="event.preventDefault(); alert('Merci ! Vous recevrez nos meilleures offres.');">
            <input type="email" required class="flex-1 px-6 py-3 rounded-lg focus:outline-none focus:ring-4 focus:ring-white/30 text-gray-800" placeholder="Votre adresse email">
            <button type="submit" class="px-8 py-3 bg-gray-900 text-white rounded-lg font-semibold hover:bg-gray-800 transition">
                <i class="fas fa-bell mr-2"></i>S'alerter
            </button>
        </form>
    </div>
</section>

<script>
// Gestion du tri
function updateSort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Mobile Filter Toggle
document.getElementById('mobileFilterBtn').addEventListener('click', function() {
    const sidebar = document.getElementById('filterSidebar');
    sidebar.classList.toggle('hidden');
    sidebar.classList.toggle('fixed');
    sidebar.classList.toggle('inset-0');
    sidebar.classList.toggle('z-50');
    sidebar.classList.toggle('bg-white');
    sidebar.classList.toggle('dark:bg-gray-800');
    sidebar.classList.toggle('p-6');
    sidebar.classList.toggle('overflow-y-auto');
    
    if (!sidebar.classList.contains('hidden')) {
        this.innerHTML = '<i class="fas fa-times"></i> Fermer les filtres';
    } else {
        this.innerHTML = '<i class="fas fa-filter"></i> Afficher les filtres';
    }
});

// Countdown Timer (24h reset)
function updateCountdown() {
    const now = new Date();
    const endOfDay = new Date();
    endOfDay.setHours(23, 59, 59, 999);
    
    const diff = endOfDay - now;
    
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    
    document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
    document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
    document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
}

setInterval(updateCountdown, 1000);
updateCountdown();
</script>

<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #475569;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>

<?php  ?>